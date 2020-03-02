<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * 2020-MM-DD TC moOde 6.5.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
$dbh = cfgdb_connect();

// Apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	// Detect changes requiring cache delete
	if ($_POST['config']['ignore_articles'] != $_SESSION['ignore_articles'] ||
		$_POST['config']['library_utf8rep'] != $_SESSION['library_utf8rep'] ||
		$_POST['config']['library_album_sort'] != $_SESSION['library_album_sort']) {

		clearLibCache();
	}

	foreach ($_POST['config'] as $key => $value) {
		cfgdb_update('cfg_system', $dbh, $key, $value);
		$_SESSION[$key] = $value;
	}

	$_SESSION['notify']['title'] = 'Changes saved';
}
session_write_close();

// Instant play action
$_select['library_instant_play'] .= "<option value=\"Add/Play\" " . (($_SESSION['library_instant_play'] == 'Add/Play') ? "selected" : "") . ">Add/Play</option>\n";
$_select['library_instant_play'] .= "<option value=\"Clear/Play\" " . (($_SESSION['library_instant_play'] == 'Clear/Play') ? "selected" : "") . ">Clear/Play</option>\n";
// Show tagview genres
$_select['library_show_genres'] .= "<option value=\"Yes\" " . (($_SESSION['library_show_genres'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['library_show_genres'] .= "<option value=\"No\" " . (($_SESSION['library_show_genres'] == 'No') ? "selected" : "") . ">No</option>\n";
// Show tagview covers
$_select['library_tagview_covers'] .= "<option value=\"Yes\" " . (($_SESSION['library_tagview_covers'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['library_tagview_covers'] .= "<option value=\"No\" " . (($_SESSION['library_tagview_covers'] == 'No') ? "selected" : "") . ">No</option>\n";
// Album sort order
$_select['library_album_sort'] .= "<option value=\"Album\" " . (($_SESSION['library_album_sort'] == 'Album') ? "selected" : "") . ">by Album</option>\n";
$_select['library_album_sort'] .= "<option value=\"Artist\" " . (($_SESSION['library_album_sort'] == 'Artist') ? "selected" : "") . ">by Artist</option>\n";
$_select['library_album_sort'] .= "<option value=\"Artist/Year\" " . (($_SESSION['library_album_sort'] == 'Artist/Year') ? "selected" : "") . ">by Artist/Year</option>\n";
$_select['library_album_sort'] .= "<option value=\"Year\" " . (($_SESSION['library_album_sort'] == 'Year') ? "selected" : "") . ">by Year</option>\n";
// Compilation identifier
$_select['library_comp_id'] = $_SESSION['library_comp_id'];
// Recently added NOTE: library_recently_added is in milliseconds, 1 day = 86400000 ms
$_select['library_recently_added'] .= "<option value=\"604800000\" " . (($_SESSION['library_recently_added'] == '604800000') ? "selected" : "") . ">1 Week</option>\n";
$_select['library_recently_added'] .= "<option value=\"2592000000\" " . (($_SESSION['library_recently_added'] == '2592000000') ? "selected" : "") . ">1 Month</option>\n";
$_select['library_recently_added'] .= "<option value=\"7776000000\" " . (($_SESSION['library_recently_added'] == '7776000000') ? "selected" : "") . ">3 Months</option>\n";
$_select['library_recently_added'] .= "<option value=\"15552000000\" " . (($_SESSION['library_recently_added'] == '15552000000') ? "selected" : "") . ">6 Months</option>\n";
$_select['library_recently_added'] .= "<option value=\"31536000000\" " . (($_SESSION['library_recently_added'] == '31536000000') ? "selected" : "") . ">1 year</option>\n";
// Ignore articles
$_select['ignore_articles'] = empty($_SESSION['ignore_articles']) ? 'None' : $_SESSION['ignore_articles'];
// Library utf8 replace
$_select['library_utf8rep'] .= "<option value=\"Yes\" " . (($_SESSION['library_utf8rep'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['library_utf8rep'] .= "<option value=\"No\" " . (($_SESSION['library_utf8rep'] == 'No') ? "selected" : "") . ">No</option>\n";
// Hi-res thumbnails
$_select['library_hiresthm'] .= "<option value=\"Auto\" " . (($_SESSION['library_hiresthm'] == 'Auto') ? "selected" : "") . ">Auto</option>\n";
$_select['library_hiresthm'] .= "<option value=\"100px\" " . (($_SESSION['library_hiresthm'] == '100px') ? "selected" : "") . ">100 px</option>\n";
$_select['library_hiresthm'] .= "<option value=\"200px\" " . (($_SESSION['library_hiresthm'] == '200px') ? "selected" : "") . ">200 px</option>\n";
$_select['library_hiresthm'] .= "<option value=\"300px\" " . (($_SESSION['library_hiresthm'] == '300px') ? "selected" : "") . ">300 px</option>\n";
$_select['library_hiresthm'] .= "<option value=\"400px\" " . (($_SESSION['library_hiresthm'] == '400px') ? "selected" : "") . ">400 px</option>\n";
// Cover search prioroty
$_select['library_covsearchpri'] .= "<option value=\"Embedded cover\" " . (($_SESSION['library_covsearchpri'] == 'Embedded cover') ? "selected" : "") . ">Embedded cover</option>\n";
$_select['library_covsearchpri'] .= "<option value=\"Cover image file\" " . (($_SESSION['library_covsearchpri'] == 'Cover image file') ? "selected" : "") . ">Cover image file</option>\n";

waitWorker(1, 'lop-config');

$tpl = "lop-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('/var/local/www/header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
