<?php
session_start();
session_unset();
session_destroy();
header("Location: index_user.php"); // hoặc trang bạn muốn quay về
exit();
?>