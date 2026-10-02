<?php
session_start();
require_once("../config.php");
require_once("../connect_db.php");

// Nếu đã login, chuyển tới dashboard
if(isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."index.php");
    exit();
}
if($_SERVER["REQUEST_METHOD"]=="POST"){
    $username=$_POST['TenDangNhap']??'';
    $password=$_POST['MatKhau']??'';
    $conn=connect_db();
    if($conn){
        $sql="SELECT *
            FROM administrator
            WHERE TenDangNhap='".$username."' AND MatKhau='".md5($password)."'";
        $result=mysqli_query($conn,$sql);
        if(mysqli_num_rows($result)>0){
            $_SESSION['admin']=$username;
            header("Location: ".ADMIN_URL."index.php");
            exit();
        } else {
            $_SESSION['err_admin']="Tên đăng nhập hoặc mật khẩu không đúng";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin</title>
    <link rel="stylesheet" href="SignUp_LogIn_Form.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Poppins", sans-serif;
        text-decoration: none;
        list-style: none;
    }

    body {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background: linear-gradient(90deg, #e2e2e2, #93acff);
    }

    .container {
        position: relative;
        width: 850px;
        height: 550px;
        background: #fff;
        margin: 20px;
        border-radius: 30px;
        box-shadow: 0 0 30px rgba(0, 0, 0, .2);
        overflow: hidden;
    }

    .container h1 {
        font-size: 36px;
        margin: -10px 0;
    }

    form {
        width: 100%;
    }

    .form-box {
        position: absolute;
        right: 0;
        width: 50%;
        height: 100%;
        background: #fff;
        display: flex;
        align-items: center;
        color: #333;
        text-align: center;
        padding: 40px;
        z-index: 1;
        transition: .6s ease-in-out 1.2s, visibility 0s 1s;
    }

    .input-box {
        position: relative;
        margin: 30px 0;
    }

    .input-box input {
        width: 100%;
        padding: 13px 50px 13px 20px;
        background: #eee;
        border-radius: 8px;
        border: none;
        outline: none;
        font-size: 16px;
        color: #333;
        font-weight: 500;
    }

    .input-box i {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 20px;
    }

    .btn {
        width: 100%;
        height: 48px;
        background: #7494ec;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 16px;
        color: #fff;
        font-weight: 600;
    }

    .btn:hover {
        background: linear-gradient(90deg, #0d66ae, #4873ff);
    }


    .toggle-box::before {
        content: '';
        position: absolute;
        left: -250%;
        width: 300%;
        height: 100%;
        background: #7494ec;
        border-radius: 150px;
        z-index: 2;
    }

    .toggle-panel {
        position: absolute;
        width: 50%;
        height: 100%;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 2;
    }
    .error {
        color: red;
        font-weight: bold;
        display: none;
    }
    .error.active{
        display: block;
    }
</style>
</head>
<body>
    <div class="container">
        <div class="form-box login">
            <form action="login_admin.php" method="post">
                <h1>Login Admin</h1>
                <div class="input-box">
                    <input type="text" placeholder="Username" required name="TenDangNhap">
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="password" placeholder="Password" required name="MatKhau">
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <span class="error <?php if(isset($_SESSION['err_admin'])) echo 'active' ?>">
                <?php   
                    if (isset($_SESSION['err_admin'])) {
                        echo $_SESSION['err_admin'];
                        unset($_SESSION['err_admin']);
                    }
                ?>
                </span>
                <input type="submit" value="Đăng nhập" class="btn">
            </form>
        </div>
        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1 class="animate__bounceIn">Hello, Welcome!</h1>
            </div>
        </div>
    </div>
</body>
</html>
