<?php
/*
 * Copyright 2005-2011 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 * SVN : $URL$
 * SVN : $Id$
 *
 */

	if (!isset($oreon))
		exit();

	if (!is_dir($nagiosCFGPath.$tab['id']."/")) {
		mkdir($nagiosCFGPath.$tab['id']."/");
	}

	/*
	 * Create file
	 */
	$handle = create_file($nagiosCFGPath.$tab['id']."/nagiosCFG.DEBUG", $oreon->user->get_name(), false);

	/*
	 * Get all information for nagios.cfg for this poller
	 */
	$DBRESULT = $pearDB->query("SELECT * FROM `cfg_nagios` WHERE `nagios_activate` = '1' AND `nagios_server_id` = '".$tab['id']."' LIMIT 1");
	$nagios = $DBRESULT->fetchRow();
    $cfgNagios = $nagios['cfg_file'];
    unset($nagios['cfg_file']);
	$DBRESULT->free();

	/*
	 * Get broker module informations
	 */
	$DBRESULT = $pearDB->query("SELECT broker_module FROM `cfg_nagios_broker_module` WHERE `cfg_nagios_id` = '".$nagios["nagios_id"]."'");
	$nagios["broker_module"] = NULL;
	while ($arBk = $DBRESULT->fetchRow()) {
		$nagios["broker_module"][] = $arBk;
	}
	$DBRESULT->free();

	$str = NULL;

	$ret["comment"] ? ($str .= "# '".$nagios["nagios_name"]."'\n") : NULL;
	if ($ret["comment"] && $nagios["nagios_comment"])	{
		$comment = array();
		$comment = explode("\n", $nagios["nagios_comment"]);
		foreach ($comment as $cmt) {
			$str .= "# ".$cmt."\n";
		}
	}

	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/hosts.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/hostTemplates.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/serviceTemplates.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/services.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/misccommands.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/checkcommands.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/contactgroups.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/contactTemplates.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/contacts.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/hostgroups.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/servicegroups.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/timeperiods.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/escalations.cfg\n";
	$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/dependencies.cfg\n";
    
    if (isset($tab['monitoring_engine']) && $tab['monitoring_engine'] == "CENGINE")
        $str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/connectors.cfg\n";

	/*
	 * Include for Meta Service the cfg file
	 * Include shinken broker cfg if necessary
	 */
	if (isset($tab['localhost']) && $tab['localhost']) {
	    if (isset($tab['monitoring_engine']) && $tab['monitoring_engine'] == "SHINKEN") {
            $str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/shinkenBroker.cfg\n";
		}
	    if ($files = glob("./include/configuration/configGenerate/metaService/*.php")) {
			foreach ($files as $filename)	{
				$cfg = NULL;
				$file = basename($filename);
				$file = explode(".", $file);
				$cfg .= $file[0];
				$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/".$cfg.".cfg\n";
			}
	    }
	}

	# Include for Module the cfg file
	foreach ($oreon->modules as $name => $tab2) {
		if ($oreon->modules[$name]["gen"] && $files = glob("./modules/$name/generate_files/*.php")) {
			foreach ($files as $filename)	{
				$cfg = NULL;
				$file = basename($filename);
				$file = explode(".", $file);
				$cfg .= $file[0];
				$str .= "cfg_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/".$cfg.".cfg\n";
			}
		}
	}
	$str .= "resource_file=".$oreon->optGen["oreon_path"].$DebugPath.$tab['id']."/resource.cfg\n";

	/*
	 * Generate all parameters
	 */
	require "./include/configuration/configGenerate/genMainFile.php";

	write_in_file($handle, html_entity_decode($str, ENT_QUOTES, "UTF-8"), $nagiosCFGPath.$tab['id']."/nagiosCFG.DEBUG");
	fclose($handle);
	
	setFileMod($nagiosCFGPath.$tab['id']."/nagiosCFG.DEBUG");
	
	$DBRESULT->free();
	unset($str);
?>
