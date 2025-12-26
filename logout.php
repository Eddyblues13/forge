<?php
require_once 'config/config.php';

session_start();
session_destroy();

redirect('login.php');
