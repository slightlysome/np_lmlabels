<?php
/*
    LMLabels Nucleus plugin
    Copyright (C) 2012-2013 Leo (www.slightlysome.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
	(http://www.gnu.org/licenses/gpl-2.0.html)	
*/

	$strRel = '../../../'; 
	require($strRel . 'config.php');
	include_libs('PLUGINADMIN.php');

	$oPluginAdmin  = new PluginAdmin('LMLabels');
	$pluginURL 	   = $oPluginAdmin->plugin->getAdminURL();
	$plugid        = $oPluginAdmin->plugin->getID();
	
	_pluginDataUpgrade($oPluginAdmin);
	
	if (!($member->isLoggedIn()))
	{
		$oPluginAdmin->start();
		echo '<p>You must be logged in to use the LMLabel plugin admin area.</p>';
		$oPluginAdmin->end();
		exit;
	}

	$aAdminBlogs = $member->getAdminBlogs();

	if(!$aAdminBlogs)
	{
		$oPluginAdmin->start();
		echo '<p>You must be a have blog admin rights to use the LMLabel plugin admin area.</p>';
		$oPluginAdmin->end();
		exit;
	}

	$blogid = intRequestVar('blogid');
	
	if($blogid)
	{
		if(!in_array($blogid, $aAdminBlogs))
		{
			$oPluginAdmin->start();
			echo "<p>You don't have blog admin rights to blog ".$blogid.".</p>";
			$oPluginAdmin->end();
			exit;
		}
	}
	else
	{
		if(count($aAdminBlogs) == 1)
		{
			$blogid = $aAdminBlogs['0'];
		}
	}

	$labelid = intRequestVar('labelid');

	if($labelid)
	{
		$aLabelInfo = $oPluginAdmin->plugin->_getLabelInfo($blogid, $labelid);
	
		if(!$aLabelInfo)
		{
			$oPluginAdmin->start();
			echo "<p>The blogid and labelid don't match.</p>";
			$oPluginAdmin->end();
			exit;
		}
	}
	
	$action = requestVar('action');

	$oPluginAdmin->start("<style type='text/css'>
	<!--
		p.message {	font-weight: bold; }
		p.error { font-size: 100%; font-weight: bold; color: #880000; }
		iframe { width: 100%; height: 400px; border: 1px solid gray; }
		div.dialogbox { border: 1px solid #ddd; background-color: #F6F6F6; margin: 18px 0 1.5em 0; }
		div.dialogbox h4 { background-color: #bbc; color: #000; margin: 0; padding: 5px; }
		div.dialogbox h4.light { background-color: #ddd; }
		div.dialogbox div { margin: 0; padding: 10px; }
		div.dialogbox button { margin: 10px 0 0 6px; float: right; }
		div.dialogbox p { margin: 0; }
		div.dialogbox p.buttons { text-align: right; overflow: auto; }
	-->
	</style>");

	$plugName = $oPluginAdmin->plugin->getName();

	if($action == 'showhelp')
	{
        echo '<p><a href="'.$pluginURL.'?skipupgradehandling=1">(Back to '.htmlspecialchars($plugName, ENT_QUOTES, _CHARSET).' administration)</a></p>';
		echo '<h2>Helppage for plugin: '.htmlspecialchars($plugName, ENT_QUOTES, _CHARSET).'</h2>';
	
		$helpFile = $DIR_PLUGINS.$oPluginAdmin->plugin->getShortName().'/help.html';
		
       if (@file_exists($helpFile)) 
	   {
            @readfile($helpFile);
        } 
		else 
		{
            echo '<p class="error">Missing helpfile.</p>';
        }
		
		$oPluginAdmin->end();
		exit;
	}

	echo '<h2>'.htmlspecialchars($plugName, ENT_QUOTES, _CHARSET).' Administration</h2>';

	if(!$blogid)
	{
		lSelectBlog($aAdminBlogs);
	}
	else
	{
		echo '<p>Editing Labels for blog: <b>'.htmlspecialchars(getBlogNameFromID($blogid), ENT_QUOTES, _CHARSET).'</b> ';
		
		if(count($aAdminBlogs) > 1)
		{
			echo ' - <a href="'.$pluginURL.'" title="Change Blog">Change</a>';
		}
		echo '</p>';

		$actions = array ('edit', 'edit_process', 'delete', 'delete_process');

		if (in_array($action, $actions)) 
		{ 
			if (!$manager->checkTicket())
			{
				echo '<p class="error">Error: Bad ticket</p>';

				lShowLabels();
			} 
			else 
			{
				call_user_func('_lmlabels_' . $action);
			}
		} 
		else 
		{
			lShowLabels();
		}
	}
	
	echo '<div class="dialogbox">';
	echo '<h4 class="light">Plugin help page</h4>';
	echo '<div>';
	echo '<p>The help page for this plugin is available <a href="'.$pluginURL.'?action=showhelp">here</a>.</p>';
	echo '</div></div>';
	echo '<div class="dialogbox">';
	echo '<h4 class="light">Plugin options page</h4>';
	echo '<div>';
	echo '<p>The options page for this plugin is available <a href="'.$CONF['AdminURL'].'index.php?action=pluginoptions&plugid='.$plugid.'">here</a>.</p>';
	echo '</div></div>';

	$oPluginAdmin->end();
	exit;

	function lShowLabels()
	{
		global $oPluginAdmin, $manager, $pluginURL, $blogid;
		
		echo '<table><thead><tr>';
		echo '<th>Label</th><th>Used</th><th colspan="2">Actions</th>';
		echo '</tr></thead>';

		$aLabelInfo = $oPluginAdmin->plugin->_getLabelInfo($blogid);

		foreach ($aLabelInfo as $aLabel)
		{
			$editURL = $manager->addTicketToUrl($pluginURL . '?action=edit&labelid='.$aLabel['labelid'].'&blogid='.$blogid);
			$editLink = '<a href="'.$editURL.'" title="Edit &quot;'.htmlspecialchars($aLabel['labelname'], ENT_QUOTES, _CHARSET).'&quot;">Edit</a>';
			
			IF($aLabel['count'] == 0)
			{
				$deleteURL = $manager->addTicketToUrl($pluginURL . '?action=delete&labelid='.$aLabel['labelid'].'&blogid='.$blogid);
				$deleteLink = '<a href="'.$deleteURL.'" title="Delete &quot;'.htmlspecialchars($aLabel['labelname'], ENT_QUOTES, _CHARSET).'&quot;">Delete</a>';
			}
			else
			{
				$deleteURL = "";
				$deleteLink = " ";
			}
			
			echo '<tr onmouseover="focusRow(this);" onmouseout="blurRow(this);">';
			echo '<td>'.htmlspecialchars($aLabel['labelname'], ENT_QUOTES, _CHARSET).'</td><td>'.$aLabel['count'].'</td><td>'.$editLink.'</td><td>'.$deleteLink.'</td>';
			echo '</tr>';		
		}
		echo '</table>';
	}

	function lSelectBlog($aBlogs)
	{
		global $pluginURL;

		echo '<table><thead><tr>';
		echo '<th>Blog</th><th>Action</th>';
		echo '</tr></thead>';

		$aBlogsName = array();
		foreach($aBlogs AS $bid)
		{
			$aBlogsName[$bid] = getBlogNameFromID($bid);
		}
		
		asort($aBlogsName);

		foreach($aBlogsName AS $bid => $bname )
		{
			$editURL = $pluginURL . '?blogid='.$bid;
			$editLink = '<a href="'.$editURL.'" title="Edit labels for &quot;'.htmlspecialchars($bname, ENT_QUOTES, _CHARSET).'&quot;">Edit Labels</a>';

			echo '<tr onmouseover="focusRow(this);" onmouseout="blurRow(this);">';
			echo '<td>'.htmlspecialchars($bname, ENT_QUOTES, _CHARSET).'</td><td>'.$editLink.'</td>';
			echo '</tr>';		
		}
		
		echo '</table>';
	}
	
	function _lmlabels_edit($labelname = '')
	{
		global $oPluginAdmin, $manager, $pluginURL, $blogid, $labelid, $aLabelInfo;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		$aLabel = $aLabelInfo['0'];

		if($labelname == '')
		{
			$labelname = $aLabel['labelname'];
		}
		
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.$pluginURL.'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="edit_process" />';
		echo '<input type="hidden" name="labelid" value="'.$labelid.'" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<input type="hidden" name="blogid" value="'.$blogid.'" />';
		echo '<h4>Edit &quot;'.htmlspecialchars($labelname, ENT_QUOTES, _CHARSET).'&quot;</h4><div>';
		echo '<p><label for="labelname">Label name:</label> ';
		echo '<input type="text" name="labelname" size="40" value="'.htmlspecialchars($labelname, ENT_QUOTES, _CHARSET).'" />';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Edit" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}

	function _lmlabels_edit_process()
	{
		global $oPluginAdmin, $manager, $pluginURL, $blogid, $labelid, $aLabelInfo;

		if (requestVar('sure') == 'yes')
		{
			$labelname = trim(requestVar('labelname'));

			$aOrgLabel = $aLabelInfo['0'];
			$orglabelname = $aOrgLabel['labelname'];

			if($orglabelname == $labelname)
			{
				echo '<p class="message">No changes to label: &quot;'.htmlspecialchars($labelname, ENT_QUOTES, _CHARSET).'&quot;.</p>';
				lShowLabels();
				return;
			}
			
			if($labelname == '') 
			{
				echo '<p class="error">Label name must have a value.</p>';
				_lmlabels_edit($labelname);
				return;
			}
			
			if($orglabelname <> $labelname)
			{
				if($oPluginAdmin->plugin->_existLabelName($blogid, $labelname, $labelid))
				{
					echo '<p class="error">New label name exists already.</p>';
					_lmlabels_edit($labelname);
					return;
				}
			}
			
			if($oPluginAdmin->plugin->_changeLabel($labelid, $labelname))
			{
				echo '<p class="message"> Updated label from &quot;'.htmlspecialchars($orglabelname, ENT_QUOTES, _CHARSET).'&quot; "
						."to &quot;'.htmlspecialchars($labelname, ENT_QUOTES, _CHARSET).'&quot;.</p>';
				lShowLabels();
			}
			else
			{
				echo '<p class="error">Update failed.</p>';
				_lmlabels_edit($labelname);
				return;
			}
		}
		else
		{
			// User cancelled
			lShowLabels();
		}
	}
	
	function _lmlabels_delete()
	{
		global $oPluginAdmin, $manager, $pluginURL, $blogid, $labelid, $aLabelInfo;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		$aLabel = $aLabelInfo['0'];

		$labelname = $aLabel['labelname'];
				
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.$pluginURL.'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="delete_process" />';
		echo '<input type="hidden" name="labelid" value="'.$labelid.'" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<input type="hidden" name="blogid" value="'.$blogid.'" />';
		echo '<h4>Delete label &quot;'.htmlspecialchars($labelname, ENT_QUOTES, _CHARSET).'&quot;?</h4><div>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Delete" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}
	
	function _lmlabels_delete_process()
	{
		global $oPluginAdmin, $manager, $pluginURL, $blogid, $labelid, $aLabelInfo;

		if (requestVar('sure') == 'yes')
		{
			$aLabel = $aLabelInfo['0'];
			$labelname = $aLabel['labelname'];

			$aUsed = $oPluginAdmin->plugin->_getItemsFromLabel($labelid);
			
			if(!empty($aUsed))
			{
				echo '<p class="error"> Can not delete label &quot;'.htmlspecialchars($labelname, ENT_QUOTES, _CHARSET).'&quot;, it is in use.</p>';
				lShowLabels();
				return;
			}

			if($oPluginAdmin->plugin->_removeLabel($labelid))
			{
				echo '<p class="message">Label: &quot;'.htmlspecialchars($labelname, ENT_QUOTES, _CHARSET).'&quot; deleted.</p>';
				lShowLabels();
			}
			else
			{
				echo '<p class="error">Delete failed.</p>';
				lShowLabels();
				return;
			}
		}
		else
		{
			// User cancelled
			lShowLabels();
		}
	}

	function _pluginDataUpgrade(&$oPluginAdmin)
	{
		global $member, $manager;
		
		if (!($member->isLoggedIn()))
		{
			// Do nothing if not logged in
			return;
		}

		$extrahead = "<style type='text/css'>
	<!--
		p.message { font-weight: bold; }
		p.error { font-size: 100%; font-weight: bold; color: #880000; }
		div.dialogbox { border: 1px solid #ddd; background-color: #F6F6F6; margin: 18px 0 1.5em 0; }
		div.dialogbox h4 { background-color: #bbc; color: #000; margin: 0; padding: 5px; }
		div.dialogbox h4.light { background-color: #ddd; }
		div.dialogbox div { margin: 0; padding: 10px; }
		div.dialogbox button { margin: 10px 0 0 6px; float: right; }
		div.dialogbox p { margin: 0; }
		div.dialogbox p.buttons { text-align: right; overflow: auto; }
	-->
	</style>";

		$pluginURL = $oPluginAdmin->plugin->getAdminURL();

		$sourcedataversion = $oPluginAdmin->plugin->getDataVersion();
		$commitdataversion = $oPluginAdmin->plugin->getCommitDataVersion();
		$currentdataversion = $oPluginAdmin->plugin->getCurrentDataVersion();
		
		$action = requestVar('action');

		$actions = array('upgradeplugindata', 'upgradeplugindata_process', 'rollbackplugindata', 'rollbackplugindata_process', 'commitplugindata', 'commitplugindata_process');

		if (in_array($action, $actions)) 
		{ 
			if (!$manager->checkTicket())
			{
				$oPluginAdmin->start($extrahead);
				echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
				echo '<p class="error">Error: Bad ticket</p>';
				$oPluginAdmin->end();
				exit;
			} 

			if (!($member->isAdmin()))
			{
				$oPluginAdmin->start($extrahead);
				echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
				echo '<p class="error">Only a super admin can execute plugin data upgrade actions.</p>';
				$oPluginAdmin->end();
				exit;
			}

			$gotoadminlink = false;
			
			$oPluginAdmin->start($extrahead);
			echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
			
			if($action == 'upgradeplugindata')
			{
				$canrollback = $oPluginAdmin->plugin->upgradeDataTest($currentdataversion, $sourcedataversion);

				$historygo = intRequestVar('historygo');
				$historygo--;
		
				echo '<div class="dialogbox">';
				echo '<form method="post" action="'.$pluginURL.'">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="upgradeplugindata_process" />';
				echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
				echo '<h4 class="light">Upgrade plugin data</h4><div>';
				echo '<p>Taking a database backup is recommended before performing the upgrade. ';
	
				if($canrollback)
				{
					echo 'After the upgrade is done you can choose to commit the plugin data to the new version or rollback the plugin data to the previous version. ';
				}
				else
				{
					echo 'This upgrade of the plugin data is not reversible. ';
				}
				
				echo '</p><br /><p>Are you sure you want to upgrade the plugin data now?</p>';
				echo '<p class="buttons">';
				echo '<input type="hidden" name="sure" value="yes" /">';
				echo '<input type="submit" value="Perform Upgrade" />';
				echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
				echo '</p>';
				echo '</div></form></div>';
			}
			else if($action == 'upgradeplugindata_process')
			{
				$canrollback = $oPluginAdmin->plugin->upgradeDataTest($currentdataversion, $sourcedataversion);

				if (requestVar('sure') == 'yes' && $sourcedataversion > $currentdataversion)
				{
					if($oPluginAdmin->plugin->upgradeDataPerform($currentdataversion + 1, $sourcedataversion))
					{
						$oPluginAdmin->plugin->setCurrentDataVersion($sourcedataversion);
						
						if(!$canrollback)
						{
							$oPluginAdmin->plugin->upgradeDataCommit($currentdataversion + 1, $sourcedataversion);
							$oPluginAdmin->plugin->setCommitDataVersion($sourcedataversion);					
						}
						
						echo '<p class="message">Upgrade of plugin data was successful.</p>';
						$gotoadminlink = true;
					}
					else
					{
						echo '<p class="error">Upgrade of plugin data failed.</p>';
					}
				}
				else
				{
					echo '<p class="message">Upgrade of plugin data canceled.</p>';
					$gotoadminlink = true;
				}
			}
			else if($action == 'rollbackplugindata')
			{
				$historygo = intRequestVar('historygo');
				$historygo--;
				
				echo '<div class="dialogbox">';
				echo '<form method="post" action="'.$pluginURL.'">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="rollbackplugindata_process" />';
				echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
				echo '<h4 class="light">Rollback plugin data upgrade</h4><div>';
				echo '<p>You may loose any plugin data added after the plugin data upgrade was performed. ';
				echo 'After the rollback is performed must you replace the plugin files with the plugin files for the previous version. ';
				echo '</p><br /><p>Are you sure you want to rollback the plugin data upgrade now?</p>';
				echo '<p class="buttons">';
				echo '<input type="hidden" name="sure" value="yes" /">';
				echo '<input type="submit" value="Perform Rollback" />';
				echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
				echo '</p>';
				echo '</div></form></div>';
			}
			else if($action == 'rollbackplugindata_process')
			{
				if (requestVar('sure') == 'yes' && $currentdataversion > $commitdataversion)
				{
					if($oPluginAdmin->plugin->upgradeDataRollback($currentdataversion, $commitdataversion + 1))
					{
						$oPluginAdmin->plugin->setCurrentDataVersion($commitdataversion);
										
						echo '<p class="message">Rollback of the plugin data upgrade was successful. You must replace the plugin files with the plugin files for the previous version before you can continue.</p>';
					}
					else
					{
						echo '<p class="error">Rollback of the plugin data upgrade failed.</p>';
					}
				}
				else
				{
					echo '<p class="message">Rollback of plugin data canceled.</p>';
					$gotoadminlink = true;
				}
			}	
			else if($action == 'commitplugindata')
			{
				$historygo = intRequestVar('historygo');
				$historygo--;
				
				echo '<div class="dialogbox">';
				echo '<form method="post" action="'.$pluginURL.'">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="commitplugindata_process" />';
				echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
				echo '<h4 class="light">Commit plugin data upgrade</h4><div>';
				echo '<p>After the commit of the plugin data upgrade is performed can you not rollback the plugin data to the previous version.</p>';
				echo '</p><br /><p>Are you sure you want to commit the plugin data now?</p>';
				echo '<p class="buttons">';
				echo '<input type="hidden" name="sure" value="yes" /">';
				echo '<input type="submit" value="Perform Commit" />';
				echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
				echo '</p>';
				echo '</div></form></div>';
			}
			else if($action == 'commitplugindata_process')
			{
				if (requestVar('sure') == 'yes' && $currentdataversion > $commitdataversion)
				{
					if($oPluginAdmin->plugin->upgradeDataCommit($commitdataversion + 1, $currentdataversion))
					{
						$oPluginAdmin->plugin->setCommitDataVersion($currentdataversion);
										
						echo '<p class="message">Commit of the plugin data upgrade was successful.</p>';
						$gotoadminlink = true;
					}
					else
					{
						echo '<p class="error">Commit of the plugin data upgrade failed.</p>';
						return;
					}
				}
				else
				{
					echo '<p class="message">Commit of plugin data canceled.</p>';
					$gotoadminlink = true;
				}
			}	
	
			if($gotoadminlink)
			{
				echo '<p><a href="'.$pluginURL.'">Continue to '.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' admin page</a>';
			}
			
			$oPluginAdmin->end();
			exit;
		}
		else
		{
			if($currentdataversion > $sourcedataversion)
			{
				$oPluginAdmin->start($extrahead);
				echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
				echo '<p class="error">An old version of the plugin files are installed. Downgrade of the plugin data is not supported.</p>';
				$oPluginAdmin->end();
				exit;
			}
			else if($currentdataversion < $sourcedataversion)
			{
				// Upgrade
				if (!($member->isAdmin()))
				{
					$oPluginAdmin->start($extrahead);
					echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
					echo '<p class="error">The plugin data needs to be upgraded before the plugin can be used. Only a super admin can do this.</p>';
					$oPluginAdmin->end();
					exit;
				}
				
				$oPluginAdmin->start($extrahead);
				echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
				echo '<div class="dialogbox">';
				echo '<h4 class="light">Upgrade plugin data</h4><div>';
				echo '<form method="post" action="'.$pluginURL.'">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="upgradeplugindata" />';
				echo '<p>The plugin data need to be upgraded before the plugin can be used. ';
				echo 'This function will upgrade the plugin data to the latest version.</p>';
				echo '<p class="buttons"><input type="submit" value="Upgrade" />';
				echo '</p></form></div></div>';
				$oPluginAdmin->end();
				exit;
			}
			else
			{
				$skipupgradehandling = (strstr(serverVar('REQUEST_URI'), '?') || serverVar('QUERY_STRING') || strtoupper(serverVar('REQUEST_METHOD') ) == 'POST');
							
				if($commitdataversion < $currentdataversion && $member->isAdmin() && !$skipupgradehandling)
				{
					// Commit or Rollback
					$oPluginAdmin->start($extrahead);
					echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
					echo '<div class="dialogbox">';
					echo '<h4 class="light">Commit plugin data upgrade</h4><div>';
					echo '<form method="post" action="'.$pluginURL.'">';
					$manager->addTicketHidden();
					echo '<input type="hidden" name="action" value="commitplugindata" />';
					echo '<p>If you choose to continue using this version after you have tested this version of the plugin, ';
					echo 'you have to choose to commit the plugin data upgrade. This function will commit the plugin data ';
					echo 'to the latest version. After the plugin data is committed will you not be able to rollback the ';
					echo 'plugin data to the previous version.</p>';
					echo '<p class="buttons"><input type="submit" value="Commit" />';
					echo '</p></form></div></div>';
					
					echo '<div class="dialogbox">';
					echo '<h4 class="light">Rollback plugin data upgrade</h4><div>';
					echo '<form method="post" action="'.$pluginURL.'">';
					$manager->addTicketHidden();
					echo '<input type="hidden" name="action" value="rollbackplugindata" />';
					echo '<p>If you choose to go back to the previous version of the plugin after you have tested this ';
					echo 'version of the plugin, you have to choose to rollback the plugin data upgrade. This function ';
					echo 'will rollback the plugin data to the previous version. ';
					echo 'After the plugin data is rolled back you have to update the plugin files to the previous version of the plugin.</p>';
					echo '<p class="buttons"><input type="submit" value="Rollback" />';
					echo '</p></form></div></div>';

					echo '<div class="dialogbox">';
					echo '<h4 class="light">Skip plugin data commit/rollback</h4><div>';
					echo '<form method="post" action="'.$pluginURL.'">';
					$manager->addTicketHidden();
					echo '<input type="hidden" name="skipupgradehandling" value="1" />';
					echo '<p>You can choose to skip the commit/rollback for now and test the new version ';
					echo 'of the plugin with upgraded data.'; 
					echo 'You will be asked to commit or rollback the plugin data upgrade the next time ';
					echo 'you use the link to the plugin admin page.</p>';
					echo '<p class="buttons"><input type="submit" value="Skip" />';
					echo '</p></form></div></div>';

					$oPluginAdmin->end();
					exit;
				}
			}
		}
	}
?>