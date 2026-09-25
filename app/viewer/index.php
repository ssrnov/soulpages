<?php
// TrackView landing — just send the viewer to the Current Status page.
// All auth/routing is handled by _boot_viewer.php (included from tracking.php).
header('Location: tracking.php');
exit;
