<?php
// System Owner Profile Wrapper
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';
require_role('owner');
redirect('owner/settings.php?tab=profile');
