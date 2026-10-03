<article class="promotion-card news-card">
    <a href="<?= e(url('news-detail.php', ['id' => $article['id']])) ?>">
        <img loading="lazy" decoding="async" width="750" height="500" src="<?= e(image_url($article['image'])) ?>" alt="">
        <div class="news-card-copy"><span class="news-card-kind"><?= e(App\Services\NewsService::CATEGORIES[$article['category']]) ?><?= $article['is_demo'] ? ' · Minh họa' : '' ?></span><h3><?= e($article['title']) ?></h3><p><?= e($article['summary']) ?></p><span class="news-read-more">Đọc bài<?= icon('arrow') ?></span></div>
    </a>
</article>
