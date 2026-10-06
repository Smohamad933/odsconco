</main>
<footer class="site-footer">
    <div class="container footer-main">
        <div class="footer-brand-block">
            <a class="brand brand-footer" href="index.php">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none">
                        <path d="M5 34.5 18.8 15l6.7 9.4 5.1-7.1L43 34.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 39h32M15.1 29.8h17.7M24 8v8M20 12h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        <circle cx="24" cy="12" r="2" fill="currentColor"/>
                    </svg>
                </span>
                <span class="brand-copy"><strong>افق دانش ثریا</strong><small>مشاوران مهندسی</small></span>
            </a>
            <p>دانش مهندسی، نگاه یکپارچه و همراهی مسئولانه؛ از نخستین ایده تا افق‌های پیش رو.</p>
            <a class="footer-login" href="login.php">ورود همکاران <span aria-hidden="true">↖</span></a>
        </div>

        <div class="footer-links">
            <h2>دسترسی سریع</h2>
            <a href="about.php">درباره ما</a>
            <a href="services.php">خدمات مهندسی</a>
            <a href="projects.php">آرشیو پروژه‌ها</a>
            <a href="contact.php">تماس با ما</a>
        </div>

        <div class="expert-contact">
            <div class="footer-heading-row">
                <h2>ارتباط با کارشناسان</h2>
                <span class="status-dot" aria-hidden="true"></span>
            </div>
            <p class="footer-note">در ساعات اداری تماس بگیرید؛ خارج از ساعات اداری از پیام‌رسان‌ها استفاده کنید.</p>
            <div class="expert-channel">
                <span class="channel-icon" aria-hidden="true">☎</span>
                <div>
                    <small>ساعات اداری · تماس تلفنی</small>
                    <?php if (site_setting('contact_phone_href') !== ''): ?>
                        <a href="<?= e(site_setting('contact_phone_href')) ?>"><?= e(site_setting('contact_phone_display')) ?></a>
                    <?php else: ?>
                        <span class="placeholder-value"><?= e(site_setting('contact_phone_display')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="expert-channel">
                <span class="channel-icon" aria-hidden="true">◉</span>
                <div>
                    <small>ساعات غیراداری · بله و تلگرام</small>
                    <div class="messenger-links">
                        <?php if (site_setting('bale_url') !== ''): ?><a href="<?= e(site_setting('bale_url')) ?>" target="_blank" rel="noopener noreferrer">بله · <?= e(site_setting('bale_display')) ?></a><?php else: ?><span class="placeholder-value">بله · <?= e(site_setting('bale_display')) ?></span><?php endif; ?>
                        <?php if (site_setting('telegram_url') !== ''): ?><a href="<?= e(site_setting('telegram_url')) ?>" target="_blank" rel="noopener noreferrer">تلگرام · <?= e(site_setting('telegram_display')) ?></a><?php else: ?><span class="placeholder-value">تلگرام · <?= e(site_setting('telegram_display')) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            <p class="office-hours"><span>ساعات کاری:</span> <?= e(site_setting('office_hours')) ?></p>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© <?= e(date('Y')) ?> <?= e(site_setting('site_name')) ?>. تمامی حقوق محفوظ است.</span>
        <a href="contact.php">آغاز یک گفت‌وگو <span aria-hidden="true">↖</span></a>
    </div>
</footer>
</body>
</html>
