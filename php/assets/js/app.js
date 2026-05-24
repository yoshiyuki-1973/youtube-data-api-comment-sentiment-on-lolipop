'use strict';

const APP_VERSION = '2026-05-14-timeout-1';
console.info(`YT Sentiment app.js ${APP_VERSION}`);

// --- DOM参照 ---
const form        = document.getElementById('analyzeForm');
const urlInput    = document.getElementById('urlInput');
const limitSelect = document.getElementById('limitSelect');
const submitBtn   = document.getElementById('submitBtn');
const errorBox    = document.getElementById('errorBox');
const loadingBox  = document.getElementById('loadingBox');
const loadingText = document.getElementById('loadingText');
const resultArea  = document.getElementById('resultArea');
const cachedBadge = document.getElementById('cachedBadge');

let chartInstance = null;
let allComments   = [];

// --- フォーム送信 ---
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    await runAnalysis();
});

async function runAnalysis() {
    const url   = urlInput.value.trim();
    const limit = parseInt(limitSelect.value, 10);

    if (!url) {
        showError('YouTube URLまたは動画IDを入力してください。');
        return;
    }

    setLoading(true, 'YouTube APIからコメントを取得中...');
    hideError();
    hideResult();

    let timeoutId = null;
    let didTimeout = false;

    try {
        setTimeout(() => setLoadingText('Geminiで感情分析中...'), 2000);

        const controller = new AbortController();
        timeoutId = setTimeout(() => {
            didTimeout = true;
            controller.abort();
            showError('分析がタイムアウトしました。コメント件数を減らして再試行してください。');
            setLoading(false);
        }, 90000);

        const res  = await fetch('api/analyze.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ url, limit }),
            signal: controller.signal,
        });
        clearTimeout(timeoutId);
        timeoutId = null;
        if (didTimeout) {
            return;
        }
        const responseText = await res.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            const detail = responseText.trim().slice(0, 300);
            showError(`サーバーからJSON以外の応答が返りました。HTTP ${res.status}${detail ? `: ${detail}` : ''}`);
            return;
        }

        if (!data.success) {
            showError(data.error ?? 'エラーが発生しました。');
            return;
        }

        try {
            renderResult(data);
        } catch (renderError) {
            console.error(renderError);
            showError(`結果表示中にエラーが発生しました: ${renderError.message}`);
        }
    } catch (err) {
        console.error(err);
        const message = err.name === 'AbortError'
            ? '分析がタイムアウトしました。コメント件数を減らして再試行してください。'
            : `サーバーへの接続に失敗しました: ${err.message}`;
        if (!didTimeout) {
            showError(message);
        }
    } finally {
        if (timeoutId !== null) {
            clearTimeout(timeoutId);
        }
        setLoading(false);
    }
}

// --- 結果レンダリング ---
function renderResult(data) {
    const { video, summary, comments, cached } = data;

    // キャッシュバッジ
    cachedBadge.hidden = !cached;

    // 動画情報
    document.getElementById('videoTitle').textContent   = video.title;
    document.getElementById('channelName').textContent  = video.channel_name;
    document.getElementById('viewCount').textContent    = formatNumber(video.view_count);
    document.getElementById('likeCount').textContent    = formatNumber(video.like_count);
    document.getElementById('commentCount').textContent = formatNumber(video.comment_count);

    // サマリー
    renderSummary(summary);

    // コメント
    allComments = comments;
    renderComments('all');

    // フィルタータブ
    setupFilterTabs();

    resultArea.hidden = false;
    resultArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderSummary(s) {
    const total = s.total_count || 1;

    setText('posCount', s.positive_count + '件');
    setText('negCount', s.negative_count + '件');
    setText('neuCount', s.neutral_count  + '件');

    setRatio('posRatio', s.positive_ratio);
    setRatio('negRatio', s.negative_ratio);
    setRatio('neuRatio', s.neutral_ratio);

    // バー幅
    setTimeout(() => {
        setBarWidth('posBar', s.positive_ratio);
        setBarWidth('negBar', s.negative_ratio);
        setBarWidth('neuBar', s.neutral_ratio);
    }, 100);

    // Chart.js ドーナツグラフ
    const chartCanvas = document.getElementById('sentimentChart');
    if (!chartCanvas || typeof Chart === 'undefined') {
        return;
    }

    const ctx = chartCanvas.getContext('2d');
    if (chartInstance) chartInstance.destroy();

    chartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['😊 ポジティブ', '😞 ネガティブ', '😐 ニュートラル'],
            datasets: [{
                data: [s.positive_count, s.negative_count, s.neutral_count],
                backgroundColor: ['#34d399', '#f87171', '#94a3b8'],
                borderColor:     ['#34d399', '#f87171', '#94a3b8'],
                borderWidth: 0,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const pct = (ctx.parsed / s.total_count * 100).toFixed(1);
                            return ` ${ctx.label}: ${ctx.parsed}件 (${pct}%)`;
                        },
                    },
                },
            },
            cutout: '65%',
        },
    });
}

function renderComments(filter) {
    const list = document.getElementById('commentList');
    list.innerHTML = '';

    const filtered = filter === 'all'
        ? allComments
        : allComments.filter(c => c.sentiment === filter);

    if (filtered.length === 0) {
        list.innerHTML = '<p style="color:var(--color-muted);text-align:center;padding:20px;">コメントがありません</p>';
        return;
    }

    filtered.forEach(c => {
        const card = document.createElement('div');
        card.className = `comment-card comment-card--${c.sentiment}`;

        const label     = sentimentLabel(c.sentiment);
        const likeText  = c.like_count > 0 ? `👍 ${c.like_count}` : '';
        const dateText  = formatDate(c.published_at);
        const metaParts = [likeText, dateText].filter(Boolean);

        card.innerHTML = `
            <div class="comment-card__header">
                <div>
                    <span class="comment-card__author">${escapeHtml(c.author || '匿名')}</span>
                    &nbsp;
                    <span class="sentiment-label sentiment-label--${c.sentiment}">${label}</span>
                </div>
                <div class="comment-card__meta">${metaParts.join(' · ')}</div>
            </div>
            <p class="comment-card__text">${escapeHtml(c.text)}</p>
            <div class="comment-card__scores">
                <span class="score-badge score-badge--pos">😊 ${pct(c.positive_score)}</span>
                <span class="score-badge score-badge--neg">😞 ${pct(c.negative_score)}</span>
                <span class="score-badge score-badge--neu">😐 ${pct(c.neutral_score)}</span>
            </div>
        `;
        list.appendChild(card);
    });
}

function setupFilterTabs() {
    const tabs = document.querySelectorAll('.filter-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('filter-tab--active'));
            tab.classList.add('filter-tab--active');
            renderComments(tab.dataset.filter);
        });
    });
    // すべてタブをアクティブに戻す
    tabs[0].classList.add('filter-tab--active');
}

// --- UI ユーティリティ ---
function setLoading(on, text = '') {
    loadingBox.hidden = !on;
    resultArea.hidden = true;
    submitBtn.disabled = on;
    if (text) setLoadingText(text);
}

function setLoadingText(text) {
    loadingText.textContent = text;
}

function showError(msg) {
    errorBox.textContent = msg;
    errorBox.hidden = false;
}

function hideError() { errorBox.hidden = true; }
function hideResult() { resultArea.hidden = true; }

function setText(id, val) { document.getElementById(id).textContent = val; }

function setRatio(id, ratio) {
    document.getElementById(id).textContent = (ratio * 100).toFixed(1) + '%';
}

function setBarWidth(id, ratio) {
    document.getElementById(id).style.width = (ratio * 100) + '%';
}

function sentimentLabel(s) {
    return s === 'pos' ? '😊 ポジティブ'
         : s === 'neg' ? '😞 ネガティブ'
         : '😐 ニュートラル';
}

function pct(score) {
    return (score * 100).toFixed(0) + '%';
}

function formatNumber(n) {
    return Number(n).toLocaleString('ja-JP');
}

function formatDate(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString('ja-JP', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return '';
    }
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
