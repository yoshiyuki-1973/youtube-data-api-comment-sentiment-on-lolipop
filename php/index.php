<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube コメント 感情分析</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="header__title">
                <span class="header__icon">▶</span>
                YouTube コメント 感情分析
            </h1>
            <p class="header__subtitle">Powered by Grok AI &amp; YouTube Data API</p>
        </div>
    </header>

    <main class="container main">

        <!-- 入力フォーム -->
        <section class="card form-card">
            <h2 class="card__title">動画を分析する</h2>
            <form id="analyzeForm" class="form">
                <div class="form__group">
                    <label class="form__label" for="urlInput">YouTube URL または 動画ID</label>
                    <input
                        type="text"
                        id="urlInput"
                        class="form__input"
                        placeholder="https://www.youtube.com/watch?v=... または 動画ID（11文字）"
                        autocomplete="off"
                        required
                    >
                </div>
                <div class="form__group form__group--inline">
                    <label class="form__label" for="limitSelect">取得コメント数</label>
                    <select id="limitSelect" class="form__select">
                        <option value="10" selected>10件</option>
                        <option value="20">20件</option>
                        <option value="30">30件</option>
                        <option value="50">50件</option>
                        <option value="100">100件</option>
                    </select>
                </div>
                <button type="submit" class="btn btn--primary" id="submitBtn">
                    分析開始
                </button>
            </form>
        </section>

        <!-- エラー表示 -->
        <div id="errorBox" class="alert alert--error" hidden></div>

        <!-- ローディング -->
        <div id="loadingBox" class="loading" hidden>
            <div class="loading__spinner"></div>
            <p class="loading__text" id="loadingText">YouTube APIからコメントを取得中...</p>
        </div>

        <!-- 結果エリア（初期非表示） -->
        <div id="resultArea" hidden>

            <!-- キャッシュ表示バッジ -->
            <div id="cachedBadge" class="badge badge--cache" hidden>
                キャッシュから取得（24時間以内の分析結果）
            </div>

            <!-- 動画情報 -->
            <section class="card">
                <h2 class="card__title" id="videoTitle"></h2>
                <p class="card__subtitle" id="channelName"></p>
                <div class="metrics">
                    <div class="metric">
                        <span class="metric__icon">👁</span>
                        <span class="metric__value" id="viewCount"></span>
                        <span class="metric__label">再生回数</span>
                    </div>
                    <div class="metric">
                        <span class="metric__icon">👍</span>
                        <span class="metric__value" id="likeCount"></span>
                        <span class="metric__label">高評価</span>
                    </div>
                    <div class="metric">
                        <span class="metric__icon">💬</span>
                        <span class="metric__value" id="commentCount"></span>
                        <span class="metric__label">コメント数</span>
                    </div>
                </div>
            </section>

            <!-- 感情分析サマリー -->
            <section class="card">
                <h2 class="card__title">感情分析サマリー</h2>
                <div class="summary-layout">
                    <div class="summary-chart">
                        <canvas id="sentimentChart" width="400" height="200"></canvas>
                    </div>
                    <div class="summary-scores">
                        <div class="score-item score-item--pos">
                            <span class="score-item__label">😊 ポジティブ</span>
                            <span class="score-item__count" id="posCount"></span>
                            <div class="score-item__bar-wrap">
                                <div class="score-item__bar" id="posBar"></div>
                            </div>
                            <span class="score-item__ratio" id="posRatio"></span>
                        </div>
                        <div class="score-item score-item--neg">
                            <span class="score-item__label">😞 ネガティブ</span>
                            <span class="score-item__count" id="negCount"></span>
                            <div class="score-item__bar-wrap">
                                <div class="score-item__bar" id="negBar"></div>
                            </div>
                            <span class="score-item__ratio" id="negRatio"></span>
                        </div>
                        <div class="score-item score-item--neu">
                            <span class="score-item__label">😐 ニュートラル</span>
                            <span class="score-item__count" id="neuCount"></span>
                            <div class="score-item__bar-wrap">
                                <div class="score-item__bar" id="neuBar"></div>
                            </div>
                            <span class="score-item__ratio" id="neuRatio"></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- コメント一覧 -->
            <section class="card">
                <div class="comments-header">
                    <h2 class="card__title">コメント一覧</h2>
                    <div class="filter-tabs" id="filterTabs">
                        <button class="filter-tab filter-tab--active" data-filter="all">すべて</button>
                        <button class="filter-tab" data-filter="pos">😊 ポジティブ</button>
                        <button class="filter-tab" data-filter="neg">😞 ネガティブ</button>
                        <button class="filter-tab" data-filter="neutral">😐 ニュートラル</button>
                    </div>
                </div>
                <div id="commentList" class="comment-list"></div>
            </section>

        </div><!-- /resultArea -->
    </main>

    <footer class="footer">
        <div class="container">
            <p>YouTube コメント感情分析 | Powered by <a href="https://x.ai" target="_blank" rel="noopener">Grok AI</a></p>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
