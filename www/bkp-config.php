<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2021 @bitlab (@bitkeeper Git)
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';

const TMP_BACKUP_ZIP = '/tmp/backup.zip';
const TMP_MOODECFG_INI = '/tmp/moodecfg.ini';
const TMP_RESTORE_ZIP = '/tmp/restore.zip';
const TMP_SCRIPT_FILE = '/tmp/script';
const BACKUP_FILE_PREFIX = 'backup';
const CAMILLADSP_BASE_DIR = '/usr/share/camilladsp/';

$backupFileNameBase = BACKUP_FILE_PREFIX . '_';

chkVariables($_POST);

//
// BACKUP
//
if (isset($_POST['backup_create']) && $_POST['backup_create'] == '1') {
	$backupOptions = '';
	if (isset($_POST['backup_system']) && $_POST['backup_system'] == '1') {
		$backupOptions .= $backupOptions ? ' config' : 'config';
	}
	if (isset($_POST['backup_camilladsp']) && $_POST['backup_camilladsp'] == '1') {
		$backupOptions .= $backupOptions ? ' cdsp' : 'cdsp';
	}
	if (isset($_POST['backup_playlists']) && $_POST['backup_playlists'] == '1') {
		$backupOptions .= $backupOptions ? ' playlists' : 'playlists';
	}
	if (isset($_POST['backup_searches']) && $_POST['backup_searches'] == '1') {
		$backupOptions .= $backupOptions ? ' searches' : 'searches';
	}
	if (isset($_POST['backup_radiostations_moode']) && $_POST['backup_radiostations_moode'] == '1') {
		$backupOptions .= $backupOptions ? ' r_moode' : 'r_moode';
	}
	if (isset($_POST['backup_radiostations_other']) && $_POST['backup_radiostations_other'] == '1') {
		$backupOptions .= $backupOptions ? ' r_other' : 'r_other';
	}

	if (empty($backupOptions)) {
		phpSession('open');
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Specify at least one item to backup.';
		phpSession('close');
	} else {
		$userID = getUserID();
		$backupOptions = '--what ' . $backupOptions . ' ';

		/*if(isset($_POST['backup_wlan0pwd']) && $_POST['backup_wlan0pwd']) {
			$backupOptions .= '--wlanpwd ' . $_POST['backup_wlan0pwd'] . ' ';
		}*/

		if (file_exists(TMP_SCRIPT_FILE)) {
			$backupOptions .= '--script ' . TMP_SCRIPT_FILE . ' ';
			sysCmd('chown ' . $userID . ':' . $userID . ' ' . TMP_SCRIPT_FILE);
		}

		// NOTE: Close the session here to avoid active session conflict if config/prefs are selected to be backed up.
		// In this case we end up with backup_manager.py -> moodeutl -e -> autocfg-gen.php which also opens the session.
		//phpSession('close');

		// Generate backup zip
		sysCmd('/var/www/util/backup_manager.py ' . $backupOptions . '--backup ' . TMP_BACKUP_ZIP);
		sysCmd('chown ' . $userID . ':' . $userID . ' ' . TMP_BACKUP_ZIP);
		//workerLog('/var/www/util/backup_manager.py ' . $backupOptions . '--backup ' . TMP_BACKUP_ZIP);

		// Create name for backup file in browser
		$dt = new DateTime('NOW');
		phpSession('open');
		$backupFileName = $backupFileNameBase . $_SESSION['hostname'] . '_' . $dt->format('ymd_Hi').'.zip';
		phpSession('close');

		header("Content-Description: File Transfer");
		header("Content-type: application/octet-stream");
		header("Content-Transfer-Encoding: binary");
		header("Content-Disposition: attachment; filename=" . $backupFileName);
		header("Content-length: " . filesize(TMP_BACKUP_ZIP));
		header("Pragma: no-cache");
		header("Expires: 0");
		readfile (TMP_BACKUP_ZIP);
		sysCmd('rm ' . TMP_BACKUP_ZIP);
		exit();
	}

//
// RESTORE
//
} else if (isset($_POST['restore_start']) && $_POST['restore_start'] == '1') {
	if (file_exists(TMP_RESTORE_ZIP)) {
		$restoreOptions = '';

		if (isset($_POST['restore_system']) && $_POST['restore_system'] == '1') {
			$restoreOptions .= $restoreOptions ? ' config' : 'config';
		}
		if (isset($_POST['restore_camilladsp']) && $_POST['restore_camilladsp'] == '1') {
			$restoreOptions .= $restoreOptions ? ' cdsp' : 'cdsp';
		}
		if (isset($_POST['restore_playlists']) && $_POST['restore_playlists'] == '1') {
			$restoreOptions .= $restoreOptions ? ' playlists' : 'playlists';
		}
		if (isset($_POST['restore_searches']) && $_POST['restore_searches'] == '1') {
			$restoreOptions .= $restoreOptions ? ' searches' : 'searches';
		}
		if (isset($_POST['restore_radiostations_moode']) && $_POST['restore_radiostations_moode'] == '1') {
			$restoreOptions .= $restoreOptions ? ' r_moode' : 'r_moode';
		}
		if (isset($_POST['restore_radiostations_other']) && $_POST['restore_radiostations_other'] == '1') {
			$restoreOptions .= $restoreOptions ? ' r_other' : 'r_other';
		}

		if (empty($restoreOptions)) {
			phpSession('open');
			$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
			$_SESSION['notify']['msg'] = 'Specify at least one item to restore.';
			phpSession('close');
		} else {
			$restoreOptions = '--what ' . $restoreOptions . ' ' .
				($_POST['restore_camilladsp_with_replace'] == '1' ? '--cdsp-replace' : '') . ' ';
			//workerLog('bkp-config: /var/www/util/backup_manager.py ' . $restoreOptions . '--restore ' . TMP_RESTORE_ZIP);
			sysCmd('/var/www/util/backup_manager.py ' . $restoreOptions . '--restore ' . TMP_RESTORE_ZIP);
			sysCmd('rm ' . TMP_RESTORE_ZIP);
			// Set permissions in case the restore doesn't require a reboot
			sysCmd('chmod 0777 ' . MPD_PLAYLIST_ROOT);
			sysCmd('chmod 0777 ' . MPD_PLAYLIST_ROOT . '*.*');
			sysCmd('chmod 0777 ' . MPD_MUSICROOT . 'RADIO/*.*');
			// Sleep for a bit to provide a "working" delay in the WebUI
			sleep(2);
			// Request reboot if system settings or radio stations are part of restore
			if (isset($_POST['restore_system']) && $_POST['restore_system'] == '1') {
				header('location: sys-restored.php');
			} else if (
				(isset($_POST['restore_radiostations_moode']) && $_POST['restore_radiostations_moode'] == '1') ||
				(isset($_POST['restore_radiostations_other']) && $_POST['restore_radiostations_other'] == '1')
			) {
				phpSession('open');
				$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
				$_SESSION['notify']['msg'] = 'Restore complete. Restart required.';
				$_SESSION['notify']['duration'] = NOTIFY_DURATION_MEDIUM;
				phpSession('close');
			} else {
				phpSession('open');
				$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
				$_SESSION['notify']['msg'] = 'Restore complete. Restart is not required.';
				$_SESSION['notify']['duration'] = NOTIFY_DURATION_MEDIUM;
				phpSession('close');
			}
		}
	} else {
		$_imported_backupfile = 'No file uploaded';
		phpSession('open');
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'Upload a backup file.';
		phpSession('close');
	}
} else if (isset($_POST['import_backupfile'])) {
	$_imported_backupfile = 'Uploaded: <b>' . $_FILES['restore_backupfile']['name'] . '</b>';
	rename($_FILES['restore_backupfile']['tmp_name'], TMP_RESTORE_ZIP);
	$_restore_disable = '';
	// NOTE: File stat is 0600/-rw-------, www-data:www-data
	//workerLog('Imported backup: ' . print_r($_FILES['restore_backupfile'], true));
} else if (isset($_POST['import_scriptfile'])) {
	$_imported_scriptfile = 'Uploaded: <b>' . $_FILES['backup_scriptfile']['name'] . '</b>';
	rename($_FILES['backup_scriptfile']['tmp_name'], TMP_SCRIPT_FILE);
	// NOTE: File stat is 0600/-rw-------, www-data:www-data
	//workerLog('Imported script: ' . print_r($_FILES['backup_scriptfile'], true));
} else if (isset($_POST['reset_options'])) {
	sysCmd('rm /tmp/backup.zip /tmp/moodecfg.ini /tmp/restore.zip /tmp/py.log /tmp/script');
	$_imported_backupfile = 'No file uploadad';
	phpSession('open');
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Options have been reset.';
	phpSession('close');
} else {
	$_imported_backupfile = 'No file uploaded';
	$_imported_scriptfile = 'No file uploaded';
}

 // Helper method to generate html code for toggle button
function genToggleButton($name, $value, $disabled, $infoHelp = true) {
	$id = str_replace('_', '-', $name);
	$template = '
	<div class="toggle" %disable_style>
		<label class="toggle-radio toggle-%id" for="toggle-%id-2">ON </label>
		<input type="radio" name="%name" id="toggle-%id-1" value="1" %checked1>
		<label class="toggle-radio toggle-%id" for="toggle-%id-1">OFF</label>
		<input type="radio" name="%name" id="toggle-%id-2" value="0" %checked0>
	</div>' . ($infoHelp === false ? '' :
		'<a aria-label="Help" class="config-info-toggle" data-cmd="info-%id" href="#notarget"><i class="fa-regular fa-sharp fa-info-circle"></i></a>');


	return strtr($template , [
		'%id' => $id,
		'%name' => $name,
		' %checked1' => ($value == True ? 'checked="checked"': ''),
		' %checked0' => ($value != True ? 'checked="checked"': ''),
		'%disable_style' => ($disabled == True ? 'style="pointer-events:none;"': '')
	]);
}

if (isset($_GET['action']) && $_GET['action'] == 'backup') {
	$_heading = 'Backup';
	$_restore_hidden = 'hidden';
	$_togglebtn_backup_system = genToggleButton('backup_system', True, False);
	$_togglebtn_backup_camilladsp = genToggleButton('backup_camilladsp', True, False);
	$_togglebtn_backup_playlists = genToggleButton('backup_playlists', True, False);
	$_togglebtn_backup_searches = genToggleButton('backup_searches', True, False);
	$_togglebtn_backup_radiostations_moode = genToggleButton('backup_radiostations_moode', True, False);
	$_togglebtn_backup_radiostations_other = genToggleButton('backup_radiostations_other', True, False);
} else if (isset($_GET['action']) && $_GET['action'] == 'restore') {
	$_heading = 'Restore';
	$_backup_hidden = 'hidden';
	$backupOptions = array();
	$backupOptions = file_exists(TMP_RESTORE_ZIP) ? sysCmd('/var/www/util/backup_manager.py --info ' . TMP_RESTORE_ZIP) : $backupOptions;
	//workerLog(print_r($backupOptions, true));
	$_togglebtn_restore_system = genToggleButton('restore_system', in_array('config', $backupOptions), !in_array('config', $backupOptions));
	$_togglebtn_restore_camilladsp = genToggleButton('restore_camilladsp', in_array('cdsp', $backupOptions), !in_array('cdsp', $backupOptions));
	$_togglebtn_restore_camilladsp_with_replace = genToggleButton('restore_camilladsp_with_replace', false, !in_array('cdsp', $backupOptions), false);
	$_togglebtn_restore_playlists = genToggleButton('restore_playlists', in_array('playlists', $backupOptions), !in_array('playlists', $backupOptions));
	$_togglebtn_restore_searches = genToggleButton('restore_searches', in_array('searches', $backupOptions), !in_array('searches', $backupOptions));
	$_togglebtn_restore_radiostations_moode = genToggleButton('restore_radiostations_moode', in_array('r_moode', $backupOptions), !in_array('r_moode', $backupOptions));
	$_togglebtn_restore_radiostations_other = genToggleButton('restore_radiostations_other', in_array('r_other', $backupOptions), !in_array('r_other', $backupOptions));
}

waitWorker('bkp-config');

$tpl = $msg == 'Restart required' ? "sys-config.html" : "bkp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
