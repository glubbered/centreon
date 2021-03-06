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

/**
 * Manage contactgroups
 */
class CentreonContactgroup
{
    private $db;

    /**
     * Constructor
     *
     * @param CentreonDB $pearDB
     */
    public function __construct($pearDB)
    {
        $this->db = $pearDB;
    }

    /**
     * Get the list of contactgroups with his id, or his name for a ldap groups if is not sync in database
     *
     * @param bool $withLdap if include LDAP group
     * @param bool $dbOnly | will not return ldap groups that are not stored in db
     * @return array
     */
    public function getListContactgroup($withLdap = false, $dbOnly = false)
    {
        /* Contactgroup from database */
        $contactgroups = array();
        $query = "SELECT a.cg_id, a.cg_name, a.cg_ldap_dn, b.ar_name FROM contactgroup a ";
        $query .= " LEFT JOIN auth_ressource b ON a.ar_id = b.ar_id";
        if (false === $withLdap) {
            $query .= " WHERE a.cg_type != 'ldap'";
        }
        $query .= " ORDER BY a.cg_name";
	    $res = $this->db->query($query);
    	while ($contactgroup = $res->fetchRow()) {
    		$contactgroups[$contactgroup["cg_id"]] = $contactgroup["cg_name"];
            if ($withLdap && isset($contactgroup['cg_ldap_dn']) && $contactgroup['cg_ldap_dn'] != "") {
                $contactgroups[$contactgroup["cg_id"]] .= " (LDAP : " . $contactgroup['ar_name'] . ")";
            }
    	}
    	$res->free();

    	$query = "SELECT `value` FROM `options` WHERE `key` = 'ldap_auth_enable'";
    	$res = $this->db->query($query);
    	$row = $res->fetchRow();
    	if ($row['value'] == 1) {
    	    $ldapEnable = true;
    	} else {
    	    $ldapEnable = false;
    	}

    	if ($withLdap && $ldapEnable && $dbOnly === false) {
            $query = "SELECT ar_id, ar_name FROM auth_ressource WHERE ar_enable = '1'";
            $ldapres = $this->db->query($query);

            /* ContactGroup from LDAP */
            while ($ldaprow = $ldapres->fetchRow()) {
                $ldap = new CentreonLDAP($this->db, null, $ldaprow['ar_id']);
                $ldap->connect(null, $ldaprow['ar_id']);
            	$cg_ldap = $ldap->listOfGroups();

                /* Merge contactgroup from ldap and from db */
            	foreach ($cg_ldap as $cg_name) {
            	    if (false === array_search($cg_name . " (LDAP : " . $ldaprow['ar_name'] . ")", $contactgroups)) {
            	        $contactgroups["[" . $ldaprow['ar_id'] . "]" . $cg_name] = $cg_name . " (LDAP : " . $ldaprow['ar_name'] . ")";
            	    }
            	}
            }
    	}
    	asort($contactgroups);
    	return $contactgroups;
    }

    /**
     * Insert the ldap groups in table contactgroups
     *
     * @param string $cg_name The ldap group name
     * @return int The contactgroup id or 0 if error
     */
    public function insertLdapGroup($cg_name)
    {
        /*
         * Parse contactgroup name
         */
        if (false === preg_match('/\[(\d+)\](.*)/', $cg_name, $matches)) {
            return 0;
        }
        $ar_id = $matches[1];
        $cg_name = $matches[2];
        /*
         * Check if contactgroup is not in databas
         */
        $queryCheck = "SELECT cg_id FROM contactgroup
            WHERE cg_name = '" . $cg_name . "' AND ar_id = " . $ar_id;
        $res = $this->db->query($queryCheck);
        if ($res->numRows() == 1) {
            $row = $res->fetchRow();
            return $row['cg_id'];
        }
        $ldap = new CentreonLDAP($this->db, null, $ar_id);
        $ldap->connect();
        $ldap_dn = $ldap->findGroupDn($cg_name);
        $query = "INSERT INTO contactgroup
        	(cg_name, cg_alias, cg_activate, cg_type, cg_ldap_dn, ar_id)
        	VALUES
        	('" . $cg_name . "', '" . $cg_name . "', '1', 'ldap', '" . $ldap_dn . "', " . $ar_id . ")";
        $res = $this->db->query($query);
        if (PEAR::isError($res)) {
            return 0;
        }
        $query = "SELECT cg_id FROM contactgroup
            WHERE cg_ldap_dn = '" . $ldap_dn . "' AND ar_id = " . $ar_id;
        $res = $this->db->query($query);
        if (PEAR::isError($res)) {
            return 0;
        }
        $row = $res->fetchRow();
        /*
         * Reset ldap build cache time
         */
        $queryCacheLdap = 'UPDATE options
            SET `value` = 0
            WHERE `key` = "ldap_last_acl_update"';
        $this->db->query($queryCacheLdap);
        return $row['cg_id'];
    }

    /**
     * Synchronize with LDAP groups
     * 
     * @return array | array of error messages
     */
    public function syncWithLdap()
    {
        $query = "SELECT ar_id FROM auth_ressource WHERE ar_enable = '1'";
        $ldapres = $this->db->query($query);

        $msg = array();
        
        /*
         * Connect to LDAP Server
         */
        while ($ldaprow = $ldapres->fetchRow()) {
            $ldapConn = new CentreonLDAP($this->db, null, $ldaprow['ar_id']);
            $connectionResult = $ldapConn->connect();
            if (false != $connectionResult) {
                $res = $this->db->query("SELECT cg_id, cg_name, cg_ldap_dn FROM contactgroup WHERE cg_type = 'ldap'");
                while ($row = $res->fetchRow()) {
                    /*
                     * Test is the group a not move or delete in ldap
                     */
                    if (false === $ldapConn->getEntry($row['cg_ldap_dn'])) {
                        $dn = $ldapConn->findGroupDn($row['cg_name']);
                        if (false === $dn) {
                            /*
                             * Delete the ldap group in contactgroup
                             */
                            $queryDelete = "DELETE FROM contactgroup WHERE cg_id = " . $row['cg_id'];
                            if (PEAR::isError($this->db->query($queryDelete))) {
                                $msg[] = "Error in delete contactgroup for ldap group : " . $row['cg_name'];
                            }
                            continue;
                        } else {
                            /*
                             * Update the ldap group in contactgroup
                             */
                            $queryUpdateDn = "UPDATE contactgroup SET cg_ldap_dn = '" . $row['cg_ldap_dn'] . "' WHERE cg_id = " . $row['cg_id'];
                            if (PEAR::isError($this->db->query($queryUpdateDn))) {
                                $msg[] = "Error in update contactgroup for ldap group : " . $row['cg_name'];
                                continue;
                            } else {
                                $row['cg_ldap_dn'] = $dn;
                            }
                        }
                    }
                    $members = $ldapConn->listUserForGroup($row['cg_ldap_dn']);

                    /*
                     * Refresh Users Groups.
                     */
                    $queryDeleteRelation = "DELETE FROM contactgroup_contact_relation WHERE contactgroup_cg_id = " . $row['cg_id'];
                    $this->db->query($queryDeleteRelation);
                    $queryContact = "SELECT contact_id FROM contact WHERE contact_ldap_dn IN ('" . join("', '", array_map('mysql_real_escape_string', $members)) . "')";
                    $resContact = $this->db->query($queryContact);
                    if (PEAR::isError($resContact)) {
                        $msg[] = "Error in getting contact id form members.";
                        continue;
                    }
                    while ($rowContact = $resContact->fetchRow()) {
                        $queryAddRelation = "INSERT INTO contactgroup_contact_relation (contactgroup_cg_id, contact_contact_id)
            	            		    VALUES (" . $row['cg_id'] . ", " . $rowContact['contact_id'] . ")";
                        if (PEAR::isError($this->db->query($queryAddRelation))) {
                            $msg[] ="Error insert relation between contactgroup " . $row['cg_id'] . " and contact " . $rowContact['contact_id'];
                        }
                    }
                }
                $queryUpdateTime = "UPDATE `options` SET `value` = '" . time() . "' WHERE `key` = 'ldap_last_acl_update'";
                $this->db->query($queryUpdateTime);
            } else {
                $msg[] = "Unable to connect to LDAP server. I keep building ACL ...";
            }
        }
        return $msg;
    }
    
    /**
     * Get contact group name from contact group id
     *
     * @param int $cgId
     * @return string
     * @throws Exception
     */
    public function getNameFromCgId($cgId)
    {
        $query = "SELECT cg_name FROM contactgroup WHERE cg_id = " . $this->db->escape($cgId) . " LIMIT 1";
        $res = $this->db->query($query);
        if ($res->numRows()) {
            $row = $res->fetchRow();
            return $row['cg_name'];
        } else {
            throw Exception('No contact group name found');
        }
    }
}
