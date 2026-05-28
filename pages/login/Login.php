<!DOCTYPE html>
<html>
<head>
    <title>Login - Best In Battle</title>
    <link rel="stylesheet" href="stylelogin.css">
</head>
<body>
    <div class="login-box">
        <form action="login.php" method="post">
            <h2>LOGIN</h2>

            <?php if (isset($_GET['error'])) { ?> 
                <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
            <?php } ?>

            <div class="input-group">
                <label>Username</label>
                <input type="text" name="uname" placeholder="Masukkan Username" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan Password" required>
            </div>

            <button type="submit">Login</button>
            
            <p class="footer">Belum punya akun? <a href="register.php">Daftar</a></p>
        </form>
    </div>
</body>
</html>