<?php
session_start();
session_unset();
session_destroy();
header("Location: ../regis/login.php");
exit;