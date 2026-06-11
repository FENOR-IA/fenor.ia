<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
session_destroy();
header('Location: login.php');
exit;
