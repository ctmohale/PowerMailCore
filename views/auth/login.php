<section class="panel compact">
    <h1>Login</h1>
    <form method="POST" action="/login">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required autofocus>
        </div>
        <div class="field" style="margin-top: 14px;">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
        </div>
        <div class="actions">
            <button type="submit">Log in</button>
        </div>
    </form>
</section>
