<?php
/**
 * Traq 2
 * Copyright (c) 2009 Jack Polgar
 * All Rights Reserved
 *
 * $Id$
 */

// Fetch required files
require('common.php');
require('../inc/version.php');
require('../inc/fishhook.class.php');
include('../inc/config.php');
include('../inc/db.class.php');

// Intro
if(!isset($_POST['step']))
{
	// Install checks
	
	// config.php check
	$checks['config.php'] = array(
		'name' => "<code>config.php</code> file",
		'class' => 'good',
		'message' => 'Found'
	);
	if(!file_exists('../inc/config.php'))
	{
		$error = true;
		$checks['config.php']['class'] = 'bad';
		$checks['config.php']['message'] = 'Not found';
	}
	
	// cache dir check
	$checks['dir_cache'] = array(
		'name' => "<code>cache</code> directory",
		'class' => 'good',
		'message' => 'Writable'
	);
	if(!is_writable('../cache'))
	{
		$error = true;
		$checks['config.php']['class'] = 'bad';
		$checks['config.php']['message'] = 'Not writable';
	}
	
	// Database check
	$checks['database'] = array(
		'name' => "Database",
		'class' => 'good',
		'message' => 'Connected'
	);
	if(mysql_connect($conf['db']['server'],$conf['db']['user'],$conf['db']['pass']))
	{
		if(!mysql_select_db($conf['db']['dbname']))
		{
			$error = true;
			$checks['database']['class'] = 'bad';
			$checks['database']['message'] = 'Cannot connect';
		}
		else
		{
			$tables = mysql_query("SHOW TABLES");
			while($info = mysql_fetch_array($tables)) {
				if($info['0'] == $conf['db']['prefix'].'settings')
				{
					$error = true;
					$checks['database']['class'] = 'bad';
					$checks['database']['message'] = 'Traq already installed';
				}
			}
		}
	}
	
	
	head('install');
	?>
	<form action="install.php" method="post">
		<input type="hidden" name="step" value="1" />
		
		<table width="400" align="center">
		<? foreach($checks as $check) { ?>
			<tr>
				<td><?=$check['name']?></td>
				<td class="<?=$check['class']?>" align="right"><?=$check['message']?></td>
			</tr>
		<? } ?>
		</table>
		
		<? if(!$error) { ?>
			<div align="center"><input type="submit" value="Next" /></div>
		<? } ?>
	</form>
	<?
	foot();
}
elseif($_POST['step'] == '1')
{	
	// Check that Traq is not already installed on the Database.
	mysql_connect($conf['db']['server'],$conf['db']['user'],$conf['db']['pass']);
	mysql_select_db($conf['db']['dbname']);
	$tables = mysql_query("SHOW TABLES");
	while($info = mysql_fetch_array($tables)) {
		if($info['0'] == $conf['db']['prefix'].'settings')
		{
			error('Install','Traq already installed');
			exit;
		}
	}
	
	head('install');
	?>
	<form action="install.php" method="post">
		<input type="hidden" name="step" value="2" />
		
		<table width="400" align="center">
			<tr>
				<td>Traq name</td>
				<td><input type="text" name="traq_name" value="Traq" /></td>
			</tr>
			<tr>
				<td>Admin Username</td>
				<td><input type="text" name="admin_name" value="Admin" /></td>
			</tr>
			<tr>
				<td>Admin Password</td>
				<td><input type="password" name="admin_pass" /></td>
			</tr>
			<tr>
				<td>Admin Email</td>
				<td><input type="text" name="admin_email" /></td>
			</tr>
		</table>
		
		<? if(!$error) { ?>
			<div align="center"><input type="submit" value="Install" /></div>
		<? } ?>
	</form>
	<?
	foot();
}
elseif($_POST['step'] == '2')
{
	// Check for errors in the fields.
	if(empty($_POST['traq_name']))
		$error = true;
	if(empty($_POST['admin_name']))
		$error = true;
	if(empty($_POST['admin_pass']))
		$error = true;
	if(empty($_POST['admin_email']))
		$error = true;
	
	if($error)
	{
		head('install');
		?>
		<table width="400" align="center">
			<tr>
				<td align="center" class="bad"><h2>Error</h2>Please fill in all fields.</td>
			</tr>
		</table>
		<?
		foot();
	}
	else
	{
		// Fetch required files.
		include('../inc/user.class.php');
		define("DBPF",$conf['db']['prefix']);
		
		// Connect to the Database.
		$db = new Database($conf['db']['server'],$conf['db']['user'],$conf['db']['pass'],$conf['db']['dbname']);
		
		// Fetch the install SQL.
		$installsql = file_get_contents('install.sql');
		$installsql = str_replace('traq_',$conf['db']['prefix'],$installsql);
		$queries = explode(';',$installsql);
		
		// Run the install queries.
		foreach($queries as $query) {
			if(!empty($query)) {
				$db->query($query);
			}
		}
		
		// Insert Settings.
		$db->query("INSERT INTO ".$conf['db']['prefix']."settings VALUES('title','".$db->res($_POST['traq_name'])."')");
		$db->query("INSERT INTO ".$conf['db']['prefix']."settings VALUES('theme','Traq2')");
		$db->query("INSERT INTO ".$conf['db']['prefix']."settings VALUES('locale','enus')");
		$db->query("INSERT INTO ".$conf['db']['prefix']."settings VALUES('single_project','0')");
		$db->query("INSERT INTO ".$conf['db']['prefix']."settings VALUES('use_recaptcha','0')");
		$db->query("INSERT INTO ".$conf['db']['prefix']."settings VALUES('recaptcha_pubkey','')");
		$db->query("INSERT INTO ".$conf['db']['prefix']."settings VALUES('recaptcha_privkey','')");
		
		// Create Admin User.
		$user = new User;
		$admindata = array(
			'login' => $_POST['admin_name'],
			'password' => $_POST['admin_pass'],
			'password2' => $_POST['admin_pass'],
			'email' => $_POST['admin_email'],
			'name' => $_POST['admin_name']
		);
		$user->register($admindata);
		$db->query("UPDATE ".$conf['db']['prefix']."users SET group_id='1' WHERE login='".$db->res($_POST['admin_name'])."' LIMIT 1");
		
		head('install');
		?>
		<table width="400" align="center">
			<tr>
				<td align="center" class="good"><h2>Installation Complete</h2>You may now login to the <a href="../admincp/">AdminCP</a> with the username and password you provided.</td>
			</tr>
		</table>
		<?
		foot();
	}
}
?>