<?php
/*
   +----------------------------------------------------------------------+
   | PEAR Web site version 1.0                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2001-2003 The PHP Group                                |
   +----------------------------------------------------------------------+
   | This source file is subject to version 2.02 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available at through the world-wide-web at                           |
   | http://www.php.net/license/2_02.txt.                                 |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Authors:                                                             |
   +----------------------------------------------------------------------+
   $Id: package-changelog.php 317218 2011-09-23 20:37:58Z pajoye $
*/
include PECL_INCLUDE_DIR . '/pear-database-package.php';

$pacid = filter_input(INPUT_GET, 'package', FILTER_SANITIZE_STRING);
if (!$pacid) {
    $pacid = filter_input(INPUT_GET, 'pacid', FILTER_SANITIZE_STRING);
}
$release = filter_input(INPUT_GET, 'release', FILTER_SANITIZE_STRING);

if (!$pacid) {
    echo $twig->render('404.html.twig');
//    response_header("Error");
//    PEAR::raiseError('Invalid package');
//    response_footer();
    exit();
}

$pkg = package::info($pacid);

if (empty($pkg['name'])) {
    echo $twig->render('404.html.twig');
//    response_header("Error");
//    PEAR::raiseError('Invalid package');
//    response_footer();
    exit();
}

//$name = $pkg['name'];
//response_header("$name Changelog");
//print '<p>' . make_link("/" . $name, 'Return') . '</p>';
//$bb = new Borderbox("Changelog for " . $name, "90%", "", 2, true);
//
//if (count($pkg['releases']) == 0) {
//    $bb->fullRow('There are no releases for ' . $name . ' yet.');
//} else {
//    $bb->headRow("Release", "What has changed?");
//
//    foreach ($pkg['releases'] as $version => $release) {
//        $link = make_link("package-info.php?package=" . $pkg['name'] .
//                          "&amp;version=" . urlencode($version), $version);
//
//        if (!empty($_GET['release']) && $version == $_GET['release']) {
//            $bb->horizHeadRow($link, nl2br($release['releasenotes']));
//        } else {
//            $bb->plainRow($link, nl2br($release['releasenotes']));
//        }
//    }
//}
//$bb->end();
//print '<p>' . make_link("/" . $name, 'Return') . '</p>';
//response_footer();

if (empty($release)) {
    $data = array_keys($pkg['releases']);
    $release = isset($data[0]) ? $data[0] : '';
}

$page = $twig->render('package-changelog.html.twig', array('package' => $pkg, 'release' => $release));
file_put_contents(__DIR__ . '/static/package/' . $pkg['name'] . ".changelog.html", $page);
echo $page;