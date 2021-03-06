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
   | Authors: Pierre Joye <pierre@php.net>                                |
   +----------------------------------------------------------------------+
   $Id: package-stats.php 317354 2011-09-26 21:07:46Z pajoye $
 */

/* TODOs

- Add chart for multiple releases or packages at the same time
- use json cache and replace all the rendering with Javascript
  . reduce traffic, reduce cpu&mem usage
*/

include PECL_INCLUDE_DIR . '/pear-database-package.php';
require_once dirname(__FILE__) . '/../include/pear-database-category.php';

$data = array();

$category_id = filter_input(INPUT_GET, "cid", FILTER_VALIDATE_INT);
$package_id  = empty($category_id) ? null : filter_input(INPUT_GET, "pid", FILTER_VALIDATE_INT);
$release_id  = empty($package_id) ? null : filter_input(INPUT_GET, "rid", FILTER_VALIDATE_INT);

$data['search']['cid'] = $category_id;
$data['search']['pid'] = $package_id;
$data['search']['rid'] = $release_id;

if ($package_id && !empty($category_id)) {
    $data['releases']  = $dbh->getAll('SELECT id, version FROM releases WHERE package = ' . $package_id, DB_FETCHMODE_ASSOC);
    $data['stat_mode'] = 'package';
    /*
    * Category based statistics
    */
} elseif (!empty($category_id)) {

    $data['category_name']     = $dbh->getOne(sprintf("SELECT name FROM categories WHERE id = %d", $category_id));
    $data['total_packages']    = $dbh->getOne(sprintf("SELECT COUNT(DISTINCT pid) FROM package_stats WHERE cid = %d", $category_id));
    $data['total_maintainers'] = $dbh->getOne(sprintf("SELECT COUNT(DISTINCT m.handle) FROM maintains m, packages p WHERE m.package = p.id AND p.category = %d", $category_id));
    $data['total_releases']    = $dbh->getOne(sprintf("SELECT COUNT(*) FROM package_stats WHERE cid = %d", $category_id));
    $data['total_categories']  = $dbh->getOne(sprintf("SELECT COUNT(*) FROM categories WHERE parent = %d", $category_id));

    // Query to get package list from package_stats_table
    $query                  = sprintf("SELECT SUM(ps.dl_number) AS download_count, ps.package as name, ps.pid as package_id, p.category as category_id
	                  FROM package_stats ps, packages p
	                  WHERE p.package_type = 'pecl' AND p.id = ps.pid AND
	                  p.category = %s GROUP BY ps.pid ORDER BY download_count DESC",
        $category_id
    );
    $data['category_stats'] = $dbh->getAll($query, NULL, DB_FETCHMODE_ASSOC);
    $data['stat_mode']      = 'category';
    /*
    * Global stats
    */
} else {

    $data['total_packages']    = number_format($dbh->getOne('SELECT COUNT(id) FROM packages WHERE package_type="pecl"'), 0, '.', ',');
    $data['total_maintainers'] = number_format($dbh->getOne('SELECT COUNT(DISTINCT handle) FROM maintains'), 0, '.', ',');
    $data['total_releases']    = number_format($dbh->getOne('SELECT COUNT(*) FROM releases r, packages p
	                   WHERE r.package = p.id AND p.package_type="pecl"'), 0, '.', ',');
    $data['total_categories']  = number_format($dbh->getOne('SELECT COUNT(*) FROM categories'), 0, '.', ',');
    $data['total_downloads']   = number_format($dbh->getOne('SELECT SUM(dl_number) FROM package_stats, packages p
                       WHERE package_stats.pid = p.id AND p.package_type="pecl"'), 0, '.', ',');
    $query                     = "SELECT sum(ps.dl_number) as download_count, ps.package as name, ps.pid as package_id, p.category as category_id
	                      FROM package_stats ps, packages p
	                      WHERE p.id = ps.pid AND p.package_type = 'pecl'
	                      GROUP BY ps.pid ORDER BY download_count DESC";
    $data['category_stats']    = $dbh->getAll($query, NULL, DB_FETCHMODE_ASSOC);
    $data['stat_mode']         = 'category';
}


$data['has_stat'] = false;

if ($data['stat_mode'] == 'package') {

    /**
     * This is the x axis labels. May change when
     * selectable dates is added.
     */
    $year   = date('Y') - 1;
    $month  = date('n') + 1;
    $x_axis = array();
    for ($i = 0; $i < 12; $i++) {
        $time                       = mktime(0, 0, 0, $month + $i, 1, $year);
        $x_axis[date('Y-m', $time)] = 0;
    }

    $sql_in = "SELECT YEAR(yearmonth) AS dyear, MONTH(yearmonth) AS dmonth, SUM(downloads) AS downloads
                    FROM aggregated_package_stats a, releases r
                    WHERE a.package_id = %d
                        AND r.id = a.release_id
                        AND r.package = a.package_id
                        AND yearmonth > (now() - INTERVAL 1 YEAR)
                        %s
                    GROUP BY dyear, dmonth
                    ORDER BY dyear DESC, dmonth DESC";

    $sql = sprintf($sql_in,
        $package_id,
        empty($release_id) ? '' : 'AND r.id = ' . $release_id);

    $chartData = array();
    $chartData[] = array('Month', 'Downloads');

    if ($result = $dbh->query($sql)) {
        while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $key = $row['dyear'] . '-' . sprintf("%1$02d", $row['dmonth']);
                if (isset($x_axis[$key])) {
                    $x_axis[$key] = (float) $row['downloads'];
                }
        }
    }

    foreach ($x_axis as $month => $download) $chartData[] = array($month, $download);

    $data['has_stat'] = true;
    $data['chartData'] = $chartData;

    $package_info                  = package::info($package_id, null, false);
    $data['package_name']          = $package_info['name'];
    $data['package_release_count'] = count($package_info['releases']);

    $query = 'SELECT s.release, SUM(s.dl_number) AS dl_number, MAX(s.last_dl) AS last_dl, MIN(r.releasedate) AS releasedate, r.id as release_id
                FROM package_stats AS s
                LEFT JOIN releases AS r ON (s.rid = r.id)
                WHERE pid = ' . $package_id;
    if ($release_id) {
        $query .= " AND rid = " . $release_id;
    }
    $query .= ' GROUP BY s.release HAVING COUNT(r.id) > 0 ORDER BY r.releasedate DESC';
    $data['release_statistic'] = $dbh->getAll($query, DB_FETCHMODE_ASSOC);

    $query                   = "SELECT SUM(dl_number) FROM package_stats WHERE pid = " . $package_id;
    $data['total_downloads'] = number_format($dbh->getOne($query), 0, '.', ',');

    $query        = "SELECT id, version FROM releases WHERE package = '" . $package_id . "'";
    $release_list = $dbh->getAll($query, DB_FETCHMODE_ASSOC);
    usort($release_list, function($a, $b) {
        return version_compare($b['version'], $a['version']);
    });
    $data['release_list'] = $release_list;
} else {
    $category_download_count = 0;
    foreach ($data['category_stats'] as $stat) {
        $category_download_count += $stat['download_count'];
    }
    $data['category_download_count'] = $category_download_count;
    $data['category_package_count']  = count($data['category_stats']);
    $data['release_list']            = array();
}

$data['category_list'] = category::listAll();

$query = "SELECT id, name FROM packages"
    . (!empty($category_id) ? " WHERE category = '" . $category_id . "'" : '')
    . " ORDER BY name";

$sth = $dbh->query($query);

while ($row = $sth->fetchRow(DB_FETCHMODE_ASSOC)) {
    $package_list[$row['id']] = $row['name'];
}
$data['package_list'] = $package_list;

$page = $twig->render('package-stats.html.twig', $data);
echo $page;
