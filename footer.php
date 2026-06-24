<?php
declare(strict_types=1);
?>
    <?php if (is_logged_in()): ?>
                </main>
                <footer class="app-footer">
                    <span><?= e(APP_NAME) ?></span>
                </footer>
            </div>
        </div>
    <?php else: ?>
        </main>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
