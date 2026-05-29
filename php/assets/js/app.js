'use strict';

const APP_VERSION = '2026-05-30-loading-fix';
console.info(`YT Sentiment app.js ${APP_VERSION}`);

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
let loadingMessageTimer = null;

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    await runAnalysis();
});

async function runAnalysis() {
    const url   = urlInput.value.trim();
    const limit = parseInt(limitSelect.value, 10);

    if (!url) {
        showError('YouTube URL または動画IDを入力してください。');
        return;
    }

    hideError();
    hideResult();
    setLoading(true, 'YouTube APIからコメントを取得中...');

    let timeoutId = null;

    try {
        loadingMessageTimer = setTimeout(() => {
            setLoadingText('Geminiで感情分析中...');
        }, 2000);

        const controller = new AbortController();
        timeoutId = setTimeout(() => {
            controller.abort();
        }, 90000);

        const res = await fetch('api/analyze.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, limit }),
            signal: controller.signal,
        });

        const responseText = await res.text();
        const data = parseJsonResponse(responseText, res.status);

        if (!res.ok || !data.success) {
            showError(data.error ?? `分析に失敗しました。HTTP ${res.status}`);
            return;
        }

        renderResult(data);
    } catch (err) {
        console.error(err);
        const message = err.name === 'AbortError'
            ? '分析がタイムアウトしました。コメント件数を減らして再試行してください。'
            : `サーバーへの接続または結果表示に失敗しました: ${err.message}`;
        showError(message);
    } finally {
        if (timeoutId !== null) {
            clearTimeout(timeoutId);
        }
        if (loadingMessageTimer !== null) {
            clearTimeout(loadingMessageTimer);
            loadingMessageTimer = null;
        }
        setLoading(false);
    }
}

function parseJsonResponse(responseText, status) {
    try {
        return JSON.parse(responseText);
    } catch {
        const detail = responseText.trim().slice(0, 300);
        throw new Error(`JSON以外の応答が返りました。HTTP ${status}${detail ? `: ${detail}` : ''}`);
    }
}

function renderResult(data) {
    const { video, summary, comments, cached } = data;

    cachedBadge.hidden = !cached;

    setText('videoTitle', video.title);
    setText('channelName', video.channel_name);
    setText('viewCount', formatNumber(video.view_count));
    setText('likeCount', formatNumber(video.like_count));
    setText('commentCount', formatNumber(video.comment_count));

    renderSummary(summary);

    allComments = Array.isArray(comments) ? comments : [];
    setupFilterTabs();
    renderComments('all');

    resultArea.hidden = false;
    resultArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderSummary(s) {
    setText('posCount', `${s.positive_count}件`);
    setText('negCount', `${s.negative_count}件`);
    setText('neuCount', `${s.neutral_count}件`);

    setRatio('posRatio', s.positive_ratio);
    setRatio('negRatio', s.negative_ratio);
    setRatio('neuRatio', s.neutral_ratio);

    setTimeout(() => {
        setBarWidth('posBar', s.positive_ratio);
        setBarWidth('negBar', s.negative_ratio);
        setBarWidth('neuBar', s.neutral_ratio);
    }, 100);

    const chartCanvas = document.getElementById('sentimentChart');
    if (!chartCanvas || typeof Chart === 'undefined') {
        return;
    }

    const ctx = chartCanvas.getContext('2d');
    if (chartInstance) {
        chartInstance.destroy();
    }

    chartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ポジティブ', 'ネガティブ', 'ニュートラル'],
            datasets: [{
                data: [s.positive_count, s.negative_count, s.neutral_count],
                backgroundColor: ['#34d399', '#f87171', '#94a3b8'],
                borderColor: ['#34d399', '#f87171', '#94a3b8'],
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
                            const total = s.total_count || 1;
                            const pct = (ctx.parsed / total * 100).toFixed(1);
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
        const likeText  = c.like_count > 0 ? `高評価 ${c.like_count}` : '';
        const dateText  = formatDate(c.published_at);
        const metaParts = [likeText, dateText].filter(Boolean);

        card.innerHTML = `
            <div class="comment-card__header">
                <div>
                    <span class="comment-card__author">${escapeHtml(c.author || '匿名')}</span>
                    &nbsp;
                    <span class="sentiment-label sentiment-label--${c.sentiment}">${label}</span>
                </div>
                <div class="comment-card__meta">${metaParts.join(' / ')}</div>
            </div>
            <p class="comment-card__text">${escapeHtml(c.text)}</p>
            <div class="comment-card__scores">
                <span class="score-badge score-badge--pos">ポジ ${pct(c.positive_score)}</span>
                <span class="score-badge score-badge--neg">ネガ ${pct(c.negative_score)}</span>
                <span class="score-badge score-badge--neu">中立 ${pct(c.neutral_score)}</span>
            </div>
        `;
        list.appendChild(card);
    });
}

function setupFilterTabs() {
    const tabs = document.querySelectorAll('.filter-tab');
    tabs.forEach(tab => {
        tab.classList.toggle('filter-tab--active', tab.dataset.filter === 'all');
        tab.onclick = () => {
            tabs.forEach(t => t.classList.remove('filter-tab--active'));
            tab.classList.add('filter-tab--active');
            renderComments(tab.dataset.filter);
        };
    });
}

function setLoading(on, text = '') {
    loadingBox.hidden = !on;
    submitBtn.disabled = on;
    if (text) {
        setLoadingText(text);
    }
}

function setLoadingText(text) {
    loadingText.textContent = text;
}

function showError(msg) {
    errorBox.textContent = msg;
    errorBox.hidden = false;
}

function hideError() {
    errorBox.hidden = true;
}

function hideResult() {
    resultArea.hidden = true;
}

function setText(id, val) {
    document.getElementById(id).textContent = val;
}

function setRatio(id, ratio) {
    document.getElementById(id).textContent = `${(Number(ratio) * 100).toFixed(1)}%`;
}

function setBarWidth(id, ratio) {
    document.getElementById(id).style.width = `${Number(ratio) * 100}%`;
}

function sentimentLabel(s) {
    return s === 'pos'
        ? 'ポジティブ'
        : s === 'neg'
            ? 'ネガティブ'
            : 'ニュートラル';
}

function pct(score) {
    return `${(Number(score) * 100).toFixed(0)}%`;
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
