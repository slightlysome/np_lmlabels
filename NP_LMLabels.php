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
	
	See lmlabels/help.html for plugin description, install, usage and change history.
*/
class NP_LMLabels extends NucleusPlugin
{
	var $labelid;
	var $urlPartTypeId;
	var $aLabelParam;
	
	// name of plugin 
	function getName()
	{
		return 'LMLabels';
	}

	// author of plugin
	function getAuthor()
	{
		return 'Leo (www.slightlysome.net)';
	}

	// an URL to the plugin website
	// can also be of the form mailto:foo@bar.com
	function getURL()
	{
		return 'http://www.slightlysome.net/nucleus-plugins/np_lmlabels';
	}

	// version of the plugin
	function getVersion()
	{
		return '1.0.1';
	}

	// a description to be shown on the installed plugins listing
	function getDescription()
	{
		return '
Label your items with one or more labels. 
These labels can be used to categorize your items. 
Labels can be used to filter items shown on the index page, archive list page or archive pages of a blog.';
	}

	function supportsFeature ($what)
	{
		switch ($what)
		{
			case 'SqlTablePrefix':
				return 1;
			case 'SqlApi':
				return 1;
			case 'HelpPage':
				return 1;
			default:
				return 0;
		}
	}
	
	function hasAdminArea()
	{
		return 1;
	}
	
	function getMinNucleusVersion()
	{
		return '360';
	}
	
	function getTableList()
	{	
		return 	array($this->getTableLabel(), $this->getTableItemLabel());
	}
	
	function getEventList() 
	{ 
		return array('EditItemFormExtras', 'PostUpdateItem', 'PostParseURL', 'QuickMenu', 
						'AddItemFormExtras', 'PostAddItem', 'PostDeleteItem', 'PostMoveItem', 'PostMoveCategory',
						'PostDeleteItem', 'AdminPrePageFoot', 'TemplateExtraFields', 
						'LMReplacementVars_CatListItemLinkPar', 
						'LMReplacementVars_BlogExtraQuery', 'LMReplacementVars_ArchiveExtraQuery',
						'LMReplacementVars_ArchListExtraQuery', 'LMReplacementVars_ArchListItemLinkPar',
						'LMBlogPaginate_LinkParams', 
						'LMFancierURL_GenerateURLParams'); 
	}
	
	function getPluginDep() 
	{
		return array('NP_LMURLParts', 'NP_LMReplacementVars');
	}
	
	function getTableLabel()
	{
		return sql_table('plug_lmlabels_label');
	}
	
	function getTableItemLabel()
	{
		return sql_table('plug_lmlabels_itemlabel');
	}

	function createTableLabel()
	{
		$query  = "CREATE TABLE IF NOT EXISTS ".$this->getTableLabel();
		$query .= "( ";
		$query .= "labelid int(11) NOT NULL auto_increment, ";
		$query .= "labelname varchar(60) NOT NULL, ";
		$query .= "blogid int(11) NOT NULL, ";
		$query .= "UNIQUE KEY labelname (labelname, blogid), ";
		$query .= "PRIMARY KEY (labelid)";
		$query .= ")";
		
		sql_query($query);
	}
	
	function createTableItemLabel()
	{
		$query  = "CREATE TABLE IF NOT EXISTS ".$this->getTableItemLabel();
		$query .= "( ";
		$query .= "labelid int(11) NOT NULL, ";
		$query .= "itemid int(11) NOT NULL, ";
		$query .= "labelorder int(11) NOT NULL, ";
		$query .= "labelmark char(1) NOT NULL, "; // I - Label should be included in item URLs (Requires NP_LMFancierURL)
		$query .= "PRIMARY KEY (labelid, itemid) ";
		$query .= ")";
		
		sql_query($query);
	}
	
	function install()
	{
		$sourcedataversion = $this->getDataVersion();

		$this->upgradeDataPerform(1, $sourcedataversion);
		$this->setCurrentDataVersion($sourcedataversion);
		$this->upgradeDataCommit(1, $sourcedataversion);
		$this->setCommitDataVersion($sourcedataversion);					
	}
	
	function unInstall()
	{
		if ($this->getOption('del_uninstall') == 'yes')	
		{
			foreach ($this->getTableList() as $table) 
			{
				sql_query("DROP TABLE IF EXISTS ".$table);
			}

			$typeid = $this->_getURLPartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);
		}
	}

	function event_AddItemFormExtras(&$data)
	{
		echo '<h3>LMLabels</h3>';
		echo '<label for="plug_lmlabels_labels">Labels:</label> <input name="plug_lmlabels_labels" id="plug_lmlabels_labels" size="100" maxlength="160" value="" />';
	}
	
	function event_EditItemFormExtras(&$data)
	{
		$itemid = $data['itemid'];
		$labelnames = "";
		
		$aLabelInfo = $this->_getLabelsFromItem($itemid, true);
		if ($aLabelInfo === false) { return false; }
		
		foreach ($aLabelInfo as $aLabel)
		{
			$labelname = $aLabel['labelname'];
			$labelmark = $aLabel['labelmark'];
			
			if($labelnames)
			{
				$labelnames .= ", ";
			}
			
			if($labelmark == 'I')
			{
				$labelname = "*".$labelname;
			}
			else if(substr($labelname, 0, 1) == '*')
			{
				$labelname = "'".$labelname;
			}
			
			$labelnames .= $labelname;
		}
			
		echo '<h3>LMLabels</h3>';
		echo '<label for="plug_lmlabels_labels">Labels:</label> <input name="plug_lmlabels_labels" id="plug_lmlabels_labels" size="100" maxlength="160" value="'.stringToAttribute($labelnames).'" />';
	}

	function event_PostAddItem(&$data)
	{
		$itemid = $data['itemid'];
		$labelorder = 0;
		
		$labels = explode(",", postVar('plug_lmlabels_labels'));
		
		foreach ($labels as $label) 
		{
			$label = trim($label);

			$labelmark = '';
			
			if(substr($label, 0, 1) == '*')
			{
				$label = substr($label, 1);
				$labelmark = 'I';
			}
			
			if(substr($label, 0, 1) == "'")
			{
				$label = substr($label, 1);
			}

			if($label)
			{
				$labelorder++;
				$this->_addLabel($label, $itemid, $labelorder, $labelmark);
			}
		}
	}

	function event_PostUpdateItem(&$data)
	{
		$itemid = $data['itemid'];
		$labelorder = 0;
		
		$labels = explode(",", postVar('plug_lmlabels_labels'));
		
		$this->_deleteItemLabel($itemid);
		
		foreach ($labels as $label) 
		{
			$label = trim($label);

			$labelmark = '';
			
			if(substr($label, 0, 1) == '*')
			{
				$label = substr($label, 1);
				$labelmark = 'I';
			}
			
			if(substr($label, 0, 1) == '\'')
			{
				$label = substr($label, 1);
			}

			if($label)
			{
				$labelorder++;
				$this->_addLabel($label, $itemid, $labelorder, $labelmark);
			}
		}
	}
	
	function event_PostMoveItem(&$data) 
	{
		$itemid = $data['itemid'];
		
		$this->_recreateLabelsForMovedItem($itemid);
	}

	function event_PostMoveCategory(&$data)
	{
		$newblogid = $data['destblog']->getID();
		$oldblogid = $data['sourceblog']->getID();
		
		$catid = $data['catid'];
		
		$aItemInfo = $this->_getItemsInCategory($catid);
		
		foreach ($aItemInfo as $aItem)
		{
			$this->_recreateLabelsForMovedItem($aItem['itemid']);
		}
	}

	function event_PostDeleteItem(&$data) 
	{
		$this->_deleteItemLabel($data['itemid']);
	}

	function event_QuickMenu(&$data) 
	{
		global $member;

		if (!$member->isAdmin() && !count($member->getAdminBlogs())) return;
			array_push($data['options'],
				array('title' => 'LMLabels',
					'url' => $this->getAdminURL(),
					'tooltip' => 'Administer NP_LMLabels'));
	}

	function event_AdminPrePageFoot(&$data)
	{
		// Workaround for missing event: AdminPluginNotification
		$data['notifications'] = array();
			
		$this->event_AdminPluginNotification($data);
			
		foreach($data['notifications'] as $aNotification)
		{
			echo '<h2>Notification from plugin: '.$aNotification['plugin'].'</h2>';
			echo $aNotification['text'];
		}
	}
	
	function event_AdminPluginNotification(&$data)
	{
		global $member, $manager;
		
		$actions = array('overview', 'pluginlist', 'plugin_LMLabels');
		$text = "";
		
		if(in_array($data['action'], $actions))
		{			
			if(!$this->_checkURLPartsSourceVersion())
			{
				$text .= '<p><b>The installed version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin needs version '.$this->_needURLPartsSourceVersion().' or later of the LMURLParts plugin to function properly.</b> The latest version of the LMURLParts plugin can be downloaded from the LMURLParts <a href="http://www.slightlysome.net/nucleus-plugins/np_lmurlparts">plugin page</a>.</p>';
			}
			elseif(!$this->_checkURLPartsDataVersion())
			{
				$text .= '<p><b>The LMURLParts plugin data needs to be upgraded before the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin can function properly.</b></p>';
			}

			if(!$this->_checkReplacementVarsSourceVersion())
			{
				$text .= '<p><b>The installed version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin needs version '.$this->_needReplacementVarsSourceVersion().' or later of the LMReplacementVars plugin to function properly.</b> The latest version of the LMReplacementVars plugin can be downloaded from the LMReplacementVars <a href="http://www.slightlysome.net/nucleus-plugins/np_lmreplacementvars">plugin page</a>.</p>';
			}

			if($manager->pluginInstalled('NP_LMFancierURL'))
			{
				if(!$this->_checkFancierURLSourceVersion())
				{
					$text .= '<p><b>The installed version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin needs version '.$this->_needFancierURLSourceVersion().' or later of the LMFancierURL plugin to function properly.</b> The latest version of the LMFancierURL plugin can be downloaded from the LMFancierURL <a href="http://www.slightlysome.net/nucleus-plugins/np_lmfancierurl">plugin page</a>.</p>';
				}
			}
			
			$sourcedataversion = $this->getDataVersion();
			$commitdataversion = $this->getCommitDataVersion();
			$currentdataversion = $this->getCurrentDataVersion();
		
			if($currentdataversion > $sourcedataversion)
			{
				$text .= '<p>An old version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin files are installed. Downgrade of the plugin data is not supported. The correct version of the plugin files must be installed for the plugin to work properly.</p>';
			}
			
			if($currentdataversion < $sourcedataversion)
			{
				$text .= '<p>The version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin data is for an older version of the plugin than the version installed. ';
				$text .= 'The plugin data needs to be upgraded or the source files needs to be replaced with the source files for the old version before the plugin can be used. ';

				if($member->isAdmin())
				{
					$text .= 'Plugin data upgrade can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.';
				}
				
				$text .= '</p>';
			}
			
			if($commitdataversion < $currentdataversion && $member->isAdmin())
			{
				$text .= '<p>The version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin data is upgraded, but the upgrade needs to commited or rolled back to finish the upgrade process. ';
				$text .= 'Plugin data upgrade commit and rollback can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.</p>';
			}
		}
		
		if($text)
		{
			array_push(
				$data['notifications'],
				array(
					'plugin' => $this->getName(),
					'text' => $text
				)
			);
		}
	}

	function event_TemplateExtraFields(&$data) 
	{
		$data['fields']['NP_LMLabels'] = array(
		
			'lmlabels_item' => 'Item Labels',
			'lmlabels_item_header' => 'Item Labels Header',
			'lmlabels_item_body' => 'Item Labels Body',
			'lmlabels_item_bodylast' => 'Item Labels Body Last',
			'lmlabels_item_none' => 'Item Labels None',
			'lmlabels_item_footer' => 'Item Labels Footer',
			'lmlabels_current' => 'Current Labels',
			'lmlabels_current_header' => 'Current Labels Header',
			'lmlabels_current_body' => 'Current Labels Body',
			'lmlabels_current_bodylast' => 'Current Labels Body Last',
			'lmlabels_current_clear' => 'Current Labels Clear',
			'lmlabels_current_none' => 'Current Labels None',
			'lmlabels_current_footer' => 'Current Labels Footer',
			'lmlabels_cloud' => 'Cloud Labels',
			'lmlabels_cloud_header' => 'Cloud Labels Header',
			'lmlabels_cloud_body' => 'Cloud Labels Body',
			'lmlabels_cloud_bodylast' => 'Cloud Labels Body Last',
			'lmlabels_cloud_none' => 'Cloud Labels None',
			'lmlabels_cloud_footer' => 'Cloud Labels Footer',
		);
	}

	function event_PostParseURL(&$data)
	{
		global $manager, $CONF, $itemid;
		
		if(!$itemid)
		{
			if($manager->pluginInstalled('NP_LMFancierURL') && $CONF['URLMode'] == 'pathinfo')
			{
				// Get params from LMFancierURL
				if(method_exists($this->_getFancierURLPlugin(), 'getURLValue'))
				{
					$this->aLabelParam = $this->_getFancierURLPlugin()->getURLValue('label');
				}
			}
			else
			{
				// Get params the normal way
				$param = requestVar('label');
				
				$this->aLabelParam = array();

				if($param)
				{
					$aParam = explode('-', $param);
					
					foreach($aParam as $labelid)
					{
						array_push($this->aLabelParam, intval($labelid));
					}
				}
			}
		}
	}

	function event_LMReplacementVars_CatListItemLinkPar(&$data)
	{
		$linkparam = $this->_getLabelLinkParam();
		
		if($linkparam)
		{
			$data['listitem']['linkparams']['label'] = $linkparam;
		}
	}

	function event_LMReplacementVars_ArchListItemLinkPar(&$data)
	{
		$linkparam = $this->_getLabelLinkParam();
		
		if($linkparam)
		{
			$data['listitem']['linkparams']['label'] = $linkparam;
		}
	}
	
	function event_LMReplacementVars_BlogExtraQuery(&$data)
	{
		$this->_setFilterExtraQuery($data);
	}
	
	function event_LMReplacementVars_ArchiveExtraQuery(&$data)
	{
		$this->_setFilterExtraQuery($data);
	}

	function event_LMReplacementVars_ArchListExtraQuery(&$data)
	{
		$this->_setFilterExtraQuery($data);
	}

	function event_LMBlogPaginate_LinkParams(&$data)
	{
		$linkparam = $this->_getLabelLinkParam();
		
		if($linkparam)
		{
			$data['linkparams']['label'] = $linkparam;
		}
	}

	function event_LMFancierURL_GenerateURLParams(&$data)
	{
		if($data['type'] == 'item')
		{
			$itemid = $data['params']['itemid'];
			$blogid = $data['params']['blogid'];
			
			if($itemid)
			{
				$itemurlurllabel = $this->getBlogOption($blogid, 'blogitemurllabel');
				
				if($itemurlurllabel == 'global')
				{
					$itemurlurllabel = $this->getOption('globalitemurllabel');
				}

				if($itemurlurllabel == 'yes')
				{
					$aParamsLabels = array();
			
					$aLabelInfo = $this->_getLabelsFromItem($itemid, true);
					if ($aLabelInfo === false) { return false; }
			
					foreach ($aLabelInfo as $aLabel)
					{
						$labelid = $aLabel['labelid'];
						$labelmark = $aLabel['labelmark'];
						
						if($labelmark == 'I')
						{
							array_push($aParamsLabels, $labelid);
						}
					}
				
					if($aParamsLabels && (!isset($data['params']['label'])))
					{
						$data['params']['label'] = $aParamsLabels;
					}
				}
			}
		}
	}
	
////////////////////////////////////////////////////////////
//  Handle vars

	function doSkinVar($skinType, $vartype, $templatename = '')
	{
		global $manager;

		$aArgs = func_get_args(); 
		$num = func_num_args();

		$aSkinVarParm = array();
		
		for($n = 3; $n < $num; $n++)
		{
			$parm = explode("=", func_get_arg($n));
			
			if(is_array($parm))
			{
				$aSkinVarParm[$parm['0']] = $parm['1'];
			}
		}

		if($templatename)
		{
			$template =& $manager->getTemplate($templatename);
		}
		else
		{
			$template = array();
		}

		switch (strtoupper($vartype))
		{
			case 'CURRENTLABELS':
				$this->doSkinVar_CurrentLabels($skinType, $template);
				break;
			case 'CLOUDLABELS':
				$this->doSkinVar_CloudLabels($skinType, $template, $aSkinVarParm);
				break;
			case 'METANOINDEX':
				$this->doSkinVar_MetaNoIndex($skinType, $template, $aSkinVarParm);
				break;
			default:
				echo "Unknown vartype: ".$vartype;
		}
	}

	function doTemplateVar(&$item, $vartype, $templatename = '')
	{
		global $manager;

		if($templatename)
		{
			$template =& $manager->getTemplate($templatename);
		}
		else
		{
			$template = array();
		}

		switch (strtoupper($vartype))
		{
			case 'ITEMLABELS':
				$this->doTemplateVar_ItemLabels($item, $template);
				break;
			default:
				echo "Unknown vartype: ".$vartype;
		}
	}

	function doSkinVar_CurrentLabels($skinType, &$template)
	{
		$localtemplate = array();
		
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_current', 'curtemplate');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_current_header', 'curtemplateheader');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_current_body', 'curtemplatebody');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_current_bodylast', 'curtemplatebodylast');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_current_footer', 'curtemplatefooter');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_current_none', 'curtemplatenone');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_current_clear', 'curtemplateclear');

		$aCurrent = array();
		$nolabelurl = $this->_createContextLink();
		
		$aLabelId = $this->aLabelParam;
		if ($aLabelId === false) { return false; }

		if(!$aLabelId && !is_array($aLabelId))
		{
			$aLabelId = array();
		}
		
		$aHeader = array();
		$aHeader['contexturl'] = $nolabelurl;
		$aHeader['labelname'] = 'None';
		
		$aFooter = array();
		$aFooter['contexturl'] = $nolabelurl;
		$aFooter['labelname'] = 'None';
		
		$labelLast = array_pop($aLabelId);
		
		$aCurrent['header'] = TEMPLATE::fill($localtemplate['lmlabels_current_header'],$aHeader);
		$aCurrent['body'] = '';
		$aCurrent['none'] = '';
		$aCurrent['clear'] = '';
		$aCurrent['footer'] = TEMPLATE::fill($localtemplate['lmlabels_current_footer'],$aFooter);

		foreach($aLabelId as $labelid)
		{
			$aLabelsInUrl = $this->aLabelParam;
			
			$key = array_search($labelid, $aLabelsInUrl);
			
			if($key !== false)
			{
				unset($aLabelsInUrl[$key]);
			}

			$extra = array('label' => $aLabelsInUrl);
			
			$aLabel = $this->_getLabelInfo(0, $labelid);
			if($aLabel === false)
			{
				return false;
			}
			$aLabel = $aLabel['0'];
			$aLabel['contexturl'] = $this->_createContextLink($extra);
			
			$aCurrent['body'] .= TEMPLATE::fill($localtemplate['lmlabels_current_body'],$aLabel);		
		}

		if($labelLast)
		{
			$labelid = $labelLast;
			$aLabelsInUrl = $this->aLabelParam;
			
			$key = array_search($labelid, $aLabelsInUrl);
			
			if($key !== false)
			{
				unset($aLabelsInUrl[$key]);
			}

			$extra = array('label' => $aLabelsInUrl);

			$aLabel = $this->_getLabelInfo(0, $labelid);
			if($aLabel === false)
			{
				return false;
			}
			$aLabel = $aLabel['0'];
			$aLabel['contexturl'] = $this->_createContextLink($extra);
			
			$aCurrent['body'] .= TEMPLATE::fill($localtemplate['lmlabels_current_bodylast'],$aLabel);

			$aLabel = array();
			$aLabel['contexturl'] = $nolabelurl;
			$aLabel['labelname'] = 'Clear all';

			$aCurrent['clear'] = TEMPLATE::fill($localtemplate['lmlabels_current_clear'],$aLabel);
		}
		else
		{
			$aLabel = array();
			$aLabel['contexturl'] = $nolabelurl;
			$aLabel['labelname'] = 'None';

			$aCurrent['none'] = TEMPLATE::fill($localtemplate['lmlabels_current_none'],$aLabel);		
		}
		
		echo TEMPLATE::fill($localtemplate['lmlabels_current'],$aCurrent);
	}

	function doSkinVar_CloudLabels($skinType, &$template, $aSkinVarParm)
	{
		global $blogid, $catid, $archive;

		$aSkinVarParmDefault = array('mediumpercent' => 50, 'largepercent' => 80, 'xlargepercent' => 95);
		
		$aSkinVarParm = array_merge($aSkinVarParmDefault, $aSkinVarParm);
		
		$localtemplate = array();
		
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_cloud', 'clotemplate');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_cloud_header', 'clotemplateheader');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_cloud_body', 'clotemplatebody');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_cloud_bodylast', 'clotemplatebodylast');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_cloud_none', 'clotemplatenone');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_cloud_footer', 'clotemplatefooter');
		
		$nolabelurl = $this->_createContextLink();

		$aLabelInfo = $this->_getLabelInfo($blogid, 0, $this->aLabelParam, $catid, $archive);
		if ($aLabelInfo === false) { return false; }

		$aTmp = $aLabelInfo;
		usort($aTmp, array("NP_LMLabels", "_usort_count"));

		$numof = Count($aTmp);
		
		$mediumkey = Intval($aSkinVarParm['mediumpercent'] * $numof / 100);
		$largekey = Intval($aSkinVarParm['largepercent'] * $numof / 100);
		$xlargekey = Intval($aSkinVarParm['xlargepercent'] * $numof / 100);
		$maxkey = $numof - 1;
		
		if($numof)
		{
			$mediumcount = $aTmp[$mediumkey]['count'];
			$largecount = $aTmp[$largekey]['count'];
			$xlargecount = $aTmp[$xlargekey]['count'];
			
			$maxcount = $aTmp[$maxkey]['count'];
		}
		else
		{
			$mediumcount = 0;
			$largecount = 0;
			$xlargecount = 0;
			$maxcount = 0;
		}
		
		if($mediumcount <= 1)
		{
			$mediumcount = 2;
		}
		
		if($mediumcount > $maxcount)
		{
			$mediumcount = $maxcount;
		}
		
		if($largecount <= $mediumcount)
		{
			$largecount = $mediumcount + 1;
		}

		if($xlargecount <= $largecount)
		{
			$xlargecount = $largecount + 1;
		}
		
		$aHeader = array();
		$aHeader['contexturl'] = $nolabelurl;
		$aHeader['labelname'] = 'None';
		
		$aFooter = array();
		$aFooter['contexturl'] = $nolabelurl;
		$aFooter['labelname'] = 'None';
		
		$aLabelLast = array_pop($aLabelInfo);
		
		$aCurrent['header'] = TEMPLATE::fill($localtemplate['lmlabels_cloud_header'],$aHeader);
		$aCurrent['body'] = '';
		$aCurrent['none'] = '';
		$aCurrent['footer'] = TEMPLATE::fill($localtemplate['lmlabels_cloud_footer'],$aFooter);
		
		foreach($aLabelInfo as $aLabel)
		{
			$labelid = $aLabel['labelid'];
			$aLabelsInUrl = $this->aLabelParam;
			
			if($aLabelsInUrl)
			{
				$key = array_search($labelid, $aLabelsInUrl);
				
				if($key === false)
				{
					array_push($aLabelsInUrl, $labelid);
				}
			}
			else
			{
				$aLabelsInUrl = array($labelid);
			}

			$count = $aLabel['count'];

			if($count >= $xlargecount)
			{
				$labelsize = 'xlarge';
			}
			else if($count >= $largecount)
			{
				$labelsize = 'large';
			}
			else if($count >= $mediumcount)
			{
				$labelsize = 'medium';
			}
			else
			{
				$labelsize = 'small';
			}

			$aLabel['size'] = $labelsize;

			$extra = array('label' => $aLabelsInUrl);
			$aLabel['contexturl'] = $this->_createContextLink($extra);

			$aCurrent['body'] .= TEMPLATE::fill($localtemplate['lmlabels_cloud_body'],$aLabel);		
		}

		if($aLabelLast)
		{
			$labelid = $aLabelLast['labelid'];
			$aLabelsInUrl = $this->aLabelParam;
			
			if($aLabelsInUrl)
			{
				$key = array_search($labelid, $aLabelsInUrl);
				
				if($key === false)
				{
					array_push($aLabelsInUrl, $labelid);
				}
			}
			else
			{
				$aLabelsInUrl = array($labelid);
			}

			$count = $aLabelLast['count'];
			
			if($count >= $xlargecount)
			{
				$labelsize = 'xlarge';
			}
			else if($count >= $largecount)
			{
				$labelsize = 'large';
			}
			else if($count >= $mediumcount)
			{
				$labelsize = 'medium';
			}
			else
			{
				$labelsize = 'small';
			}
			
			$aLabelLast['size'] = $labelsize;

			$extra = array('label' => $aLabelsInUrl);
			$aLabelLast['contexturl'] = $this->_createContextLink($extra);

			$aCurrent['body'] .= TEMPLATE::fill($localtemplate['lmlabels_cloud_bodylast'],$aLabelLast);		
		}
		else
		{
			$aLabel = array();
			$aLabel['contexturl'] = $nolabelurl;
			$aLabel['labelname'] = 'None';

			$aCurrent['none'] = TEMPLATE::fill($localtemplate['lmlabels_cloud_none'],$aLabel);		
		}

		echo TEMPLATE::fill($localtemplate['lmlabels_cloud'],$aCurrent);
	}

	function doSkinVar_MetaNoIndex($skinType, &$template, $aSkinVarParm)
	{
		global $catid;
		
		if($skinType == 'search' || (in_array($skinType, array('index', 'archive', 'archivelist')) && ($this->aLabelParam || $catid)))
		{
			echo '<meta name="robots" content="noindex" />';
		}
	}
				
	function doTemplateVar_ItemLabels(&$item, &$template)
	{
		global $itemid;

		$localtemplate = array();
		
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_item', 'itemtemplate');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_item_header', 'itemtemplateheader');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_item_body', 'itemtemplatebody');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_item_bodylast', 'itemtemplatebodylast');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_item_none', 'itemtemplatenone');
		$this->_checkSetTemplate($template, $localtemplate, 'lmlabels_item_footer', 'itemtemplatefooter');

		$nolabelurl = $this->_createContextLink();

		$aLabelInfo = $this->_getLabelsFromItem($item->itemid, true);
		if ($aLabelInfo === false) { return false; }

		$aHeader = array();
		$aHeader['contexturl'] = $nolabelurl;
		$aHeader['labelname'] = 'None';
		
		$aFooter = array();
		$aFooter['contexturl'] = $nolabelurl;
		$aFooter['labelname'] = 'None';
		
		$aLabelLast = array_pop($aLabelInfo);
		
		$aCurrent['header'] = TEMPLATE::fill($localtemplate['lmlabels_item_header'],$aHeader);
		$aCurrent['body'] = '';
		$aCurrent['none'] = '';
		$aCurrent['footer'] = TEMPLATE::fill($localtemplate['lmlabels_item_footer'],$aFooter);
		
		foreach($aLabelInfo as $aLabel)
		{
			$labelid = $aLabel['labelid'];
			$aLabelsInUrl = $this->aLabelParam;
			
			if($aLabelsInUrl)
			{
				$key = array_search($labelid, $aLabelsInUrl);
				
				if($key === false)
				{
					array_push($aLabelsInUrl, $labelid);
				}
			}
			else
			{
				$aLabelsInUrl = array($labelid);
			}

			$extra = array('label' => $aLabelsInUrl);
		
			$aLabel['contexturl'] = $this->_createContextLink($extra);

			$aCurrent['body'] .= TEMPLATE::fill($localtemplate['lmlabels_item_body'],$aLabel);		
		}

		if($aLabelLast)
		{
			$labelid = $aLabelLast['labelid'];
			$aLabelsInUrl = $this->aLabelParam;
			
			if($aLabelsInUrl)
			{
				$key = array_search($labelid, $aLabelsInUrl);
				
				if($key === false)
				{
					array_push($aLabelsInUrl, $labelid);
				}
			}
			else
			{
				$aLabelsInUrl = array($labelid);
			}

			$extra = array('label' => $aLabelsInUrl);
		
			$aLabelLast['contexturl'] = $this->_createContextLink($extra);

			$aCurrent['body'] .= TEMPLATE::fill($localtemplate['lmlabels_item_bodylast'],$aLabelLast);		
		}
		else
		{
			$aLabel = array();
			$aLabel['contexturl'] = $nolabelurl;
			$aLabel['labelname'] = 'None';

			$aCurrent['none'] = TEMPLATE::fill($localtemplate['lmlabels_item_none'],$aLabel);		
		}

		echo TEMPLATE::fill($localtemplate['lmlabels_item'],$aCurrent);
	}
	
	
////////////////////////////////////////////////////////////
//  Private functions
	function &_getURLPartPlugin()
	{
		global $manager;
		
		$oURLPartPlugin =& $manager->getPlugin('NP_LMURLParts');

		if(!$oURLPartPlugin)
		{
			// Panic
			echo '<p>Couldn\'t get plugin NP_LMURLParts. This plugin must be installed for the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin to work.</p>';
			return false;
		}
		
		return $oURLPartPlugin;
	}
	
	function &_getFancierURLPlugin()
	{
		global $manager;
		
		$oFancierURLPlugin =& $manager->getPlugin('NP_LMFancierURL');

		if(!$oFancierURLPlugin)
		{
			// Panic
			echo '<p>Couldn\'t get plugin LMFancierURL. This plugin must be installed for the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin to work.</p>';
			return false;
		}
		
		return $oFancierURLPlugin;
	}

	function &_getReplacementVarsPlugin()
	{
		global $manager;
		
		$oReplacementVarsPlugin =& $manager->getPlugin('NP_LMReplacementVars');

		if(!$oReplacementVarsPlugin)
		{
			// Panic
			echo '<p>Couldn\'t get plugin LMReplacementVars. This plugin must be installed for the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin to work.</p>';
			return false;
		}
		
		return $oReplacementVarsPlugin;
	}

	function _getURLPartTypeId()
	{
		if(!$this->urlPartTypeId)
		{
			$this->urlPartTypeId = $this->_getURLPartPlugin()->findTypeId('Label', $this->getName());
			
			if($this->urlPartTypeId === false)
			{
				return false;
			}

			if(!$this->urlPartTypeId)
			{
				$this->urlPartTypeId = $this->_getURLPartPlugin()->addType('Label', $this->getName(), 'B', 'label', 41, 'label');
			}
		}
		return $this->urlPartTypeId;
	}

	function _usort_count($a, $b)
	{
		$counta = $a['count'];
		$countb = $b['count'];
		
		if($counta == $countb)
		{
			$ret = 0;
		}
		elseif($counta > $countb)
		{
			$ret = 1;
		}
		elseif($counta < $countb)
		{
			$ret = -1;
		}
		
		return $ret;
	}

	function _addLabel($labelname, $itemid, $labelorder, $labelmark)
	{
		$blogid = getBlogIDFromItemID($itemid);
		$labelid = $this->_existLabelName($blogid, $labelname);

		$typeid = $this->_getURLPartTypeId();
		if($typeid === false)
		{
			return false;
		}
		
		if(!$labelid)
		{
			$labelid = $this->_insertLabel($labelname, $blogid);
			
			if(!$labelid)
			{
				return false;
			}

			$this->_getURLPartPlugin()->addChangeURLPart($labelname, $typeid, $labelid, $blogid);
		}

		$ret = $this->_insertItemLabelIfNotExists($labelid, $itemid, $labelorder, $labelmark);

		return $ret;
	}

	function _changeLabel($labelid, $labelname)
	{
		$aLabel = $this->_getLabelInfo(0, $labelid);
		if($aLabel === false)
		{
			return false;
		}
		$aLabel = $aLabel['0'];
		$blogid = $aLabel['blogid'];

		$typeid = $this->_getURLPartTypeId();
		if($typeid === false)
		{
			return false;
		}

		$res = $this->_updateLabel($labelid, $labelname);
		if($res === false)
		{
			return false;
		}

		$res = $this->_getURLPartPlugin()->addChangeURLPart($labelname, $typeid, $labelid, $blogid);
		if($res === false)
		{
			return false;
		}

		return true;
	}

	function _removeLabel($labelid)
	{
		$aLabel = $this->_getLabelInfo(0, $labelid);

		if($aLabel === false)
		{
			return false;
		}
		$aLabel = $aLabel['0'];
		$blogid = $aLabel['blogid'];
		$labelname = $aLabel['labelname'];

		$typeid = $this->_getURLPartTypeId();
		if($typeid === false)
		{
			return false;
		}

		$res = $this->_deleteLabel($labelid);
		if($res === false)
		{
			return false;
		}

		$res = $this->_getURLPartPlugin()->removeURLPart($labelname, $typeid, $labelid, $blogid);
		if($res === false)
		{
			return false;
		}

		return true;
	}

	function _recreateLabelsForMovedItem($itemid)
	{
		$aLabelInfo = $this->_getLabelsFromItem($itemid);

		$this->_deleteItemLabel($itemid);

		foreach ($aLabelInfo as $aLabel)
		{
			$labelname = $aLabel['labelname'];
			$labelorder = $aLabel['labelorder'];
			$labelmark = $aLabel['labelmark'];

			$this->_addLabel($labelname, $itemid, $labelorder, $labelmark);
		}
	}

	function _createContextLink($extra = '')
	{
		global $manager, $blogid, $itemid, $catid, $archive, $archivelist, $CONF;

		if(!($manager->pluginInstalled('NP_LMFancierURL') && $CONF['URLMode'] == 'pathinfo')) 
		{
			if(isset($extra['label']))
			{
				$extra['label'] = implode('-', $extra['label']);
			}
		}

		$contexturl = '';
		
		if($itemid)
		{
			if($catid)
			{
				$contexturl = createCategoryLink($catid, $extra);
			}
		} 
		else if($archive)
		{
			if($catid)
			{
				$extra['catid'] = $catid;
			}
			
			$contexturl = createArchiveLink($blogid, $archive, $extra);
		} 
		else if($archivelist)
		{
			if($catid)
			{
				$extra['catid'] = $catid;
			}
			$contexturl = createArchiveListLink($archivelist, $extra);
		}
		else if($catid)
		{
			$contexturl = createCategoryLink($catid, $extra);
		}

		if(!$contexturl)
		{
			$contexturl = createBlogidLink($blogid, $extra);
		}
		
		return $contexturl;
	}
			
	function _getLabelLinkParam()
	{
		global $manager, $CONF;
		
		$labels = $this->aLabelParam;
		
		if($labels)
		{
			if((!($manager->pluginInstalled('NP_LMFancierURL') && $CONF['URLMode'] == 'pathinfo')) && is_array($labels))
			{
				$labels = implode('-', $aLabelsInUrl);
			}
		}
		
		return $labels;
	}

	function _getLabelExtraQuery(&$blog)
	{
		global $blogid;
		
		$aLabelsInUrl = $this->aLabelParam;
		$extraquery = '';
		
		if($aLabelsInUrl && $blog->getID() == $blogid)
		{
			foreach($aLabelsInUrl as $id)
			{
				if($extraquery)
				{
					$extraquery .= ' AND ';
				}
				
				$extraquery .= 'EXISTS (SELECT 1 FROM '.$this->getTableItemLabel().' il ';
				$extraquery .= 'WHERE i.inumber = il.itemid AND il.labelid = '.$id.')';
			}
		}

		return $extraquery;
	}
		
	function _checkSetTemplate(&$globaltemplate, &$localtemplate, $index, $option)
	{
		if(isset($globaltemplate[$index]))
		{
			$val = $globaltemplate[$index];
		}
		else
		{
			$val = '';
		}
		
		if($val == '#empty#')
		{
			$val = '';
		}
		else if($val == '')
		{
			$val = $this->getOption($option);
		}
		
		$localtemplate[$index] = $val;
	}
		
	function _setFilterExtraQuery(&$data)
	{
		$skinvarparm = $data['skinvarparm'];

		if(isset($skinvarparm['lmlabelsfilter']))
		{
			$lmlabelsfilter = $skinvarparm['lmlabelsfilter'];
		}
		else 
		{
			$lmlabelsfilter = 'enable';
		}

		if($lmlabelsfilter == 'enable')
		{
			$extraquery = $this->_getLabelExtraQuery($data['blog']);
			
			if($extraquery)
			{
				$data['extraquery']['lmlabels'] = $extraquery;
			}
		}
	}

	/////////////////////////////////////////////////////
	// Data access and manipulation functions:
	
	/*
    * @returns
    *      array(
    *         array(
    *            'labelid', 'labelname', 'blogid', 'count'
    *         )
    *      )
	*/
	function _getLabelInfo($blogid, $labelid = 0, $inlabelid = '', $catid = 0, $archive = '') 
	{
		$ret = array();

		if($inlabelid)
		{
			if(!is_array($inlabelid))
			{
				$inlabelid = array($inlabelid);
			}
		}
		
		$query = "SELECT l.labelid, l.labelname, l.blogid, count(i.labelid) AS count "
			."FROM ".$this->getTableLabel()." l "
			."INNER JOIN ".$this->getTableItemLabel()." i ON  l.labelid = i.labelid ";

		if($catid || $archive)
		{
			$query .= "INNER JOIN ".sql_table('item')." item ON i.itemid = item.inumber ";
		}

		if($labelid > 0 && $blogid == 0)
		{
			$query .= "WHERE l.labelid = ".$labelid." ";
		}
		
		if($blogid > 0 && $labelid == 0)
		{
			$query .= "WHERE l.blogid = ".$blogid." ";
		}
		
		if($blogid > 0 && $labelid > 0)
		{
			$query .= "WHERE l.blogid = ".$blogid." AND l.labelid = ".$labelid." ";
		}

		if($inlabelid)
		{
			foreach($inlabelid as $id)
			{
				$query .= "AND i.itemid IN (SELECT i1.itemid FROM ".$this->getTableItemLabel()." i1 WHERE i1.labelid = ".$id.")";
			}
			
			$query .= "AND l.labelid NOT IN (".implode(',', $inlabelid).")";
		}

		if($catid)
		{
			$query .= "AND item.icat = ".$catid." ";
		}

		if($archive)
		{
			sscanf($archive,'%d-%d-%d', $year, $month, $day);

			if ($day == 0 && $month != 0) {
				$timestamp_start = mktime(0,0,0,$month,1,$year);
				$timestamp_end = mktime(0,0,0,$month+1,1,$year);  // also works when $month==12
			} elseif ($month == 0) {
				$timestamp_start = mktime(0,0,0,1,1,$year);
				$timestamp_end = mktime(0,0,0,12,31,$year);  // also works when $month==12
			} else {
				$timestamp_start = mktime(0,0,0,$month,$day,$year);
				$timestamp_end = mktime(0,0,0,$month,$day+1,$year);
			}
			$query .= "AND item.itime >= " . mysqldate($timestamp_start) . " AND item.itime < " . mysqldate($timestamp_end) . " ";
		}

		$query .= "GROUP BY l.labelid, l.labelname ORDER BY l.labelname";

		$res = sql_query($query);

		while ($o = sql_fetch_object($res))
		{
			array_push($ret, array(
				'labelid'    => $o->labelid,
				'labelname'       => $o->labelname,
				'blogid'		=> $o->blogid,
				'count'      => intVal($o->count)
				));
		}

		return $ret;
	}
	
	function _updateLabel($labelid, $labelname)
	{
		$aLabel = $this->_getLabelInfo(0, $labelid);
		$blogid = $aLabel['0']['blogid'];
	
		if($this->_existLabelName($blogid, $labelname, $labelid))
		{
			return false;
		}
		
		$query = "UPDATE ".$this->getTableLabel()." SET "
			."labelname = '".sql_real_escape_string($labelname)."' "
			."WHERE labelid = ".$labelid;
	
		$res = sql_query($query);
		
		if(!$res)
		{
			return false;
		}
		
		return true;
	}
	
	function _insertItemLabelIfNotExists($labelid, $itemid, $labelorder, $labelmark)
	{
		if(! $this->_getItemsFromLabel($labelid, $itemid))
		{
			$query = "INSERT ".$this->getTableItemLabel()." (labelid, itemid, labelorder, labelmark) "
					."VALUES (".$labelid.", ".$itemid.", ".$labelorder.", '".sql_real_escape_string($labelmark)."')";

			$res = sql_query($query);
		
			if(!$res)
			{
				return false;

			}
		}
		return true;
	}

	function _insertLabel($labelname, $blogid)
	{
		$query = "INSERT ".$this->getTableLabel()."(labelname, blogid) "
				."VALUES ('".sql_real_escape_string($labelname)."', ".$blogid.")";
					
		$res = sql_query($query);
		
		if(!$res)
		{
			return false;

		}
		
		$labelid = sql_insert_id();
		
		return $labelid;
	}
	
	function _deleteLabel($labelid)
	{
		$aLabelUsed = $this->_getItemsFromLabel($labelid);

		if(!is_array($aLabelUsed)) // db-error
		{
			return false;
		}
		
		if(!empty($aLabelUsed)) // if used
		{
			return false;
		}
		
		$query = "DELETE FROM ".$this->getTableLabel()." WHERE labelid = ".$labelid;
		
		$res = sql_query($query);

		if(!$res)
		{
			return false;
		}
		
		return true;
	}

	function _deleteItemLabel($itemid)
	{
		$query = "DELETE FROM ".$this->getTableItemLabel()." WHERE itemid = ".$itemid;
		
		$res = sql_query($query);

		if(!$res)
		{
			return false;
		}
		
		return true;
	}

	function _existLabelName($blogid, $labelname, $notlabelid = 0)
	{
		$labelid = 0;
		
		$query = "SELECT l.labelid FROM ".$this->getTableLabel()." l "
				."WHERE l.labelname = '".sql_real_escape_string($labelname)."' "
				."AND l.blogid = ".$blogid." ";

		if($notlabelid)
		{
			$query .= "AND l.labelid <> ".$notlabelid." ";
		}
		
		$res = sql_query($query);
		
		while ($o = sql_fetch_object($res))
		{
			$labelid = $o->labelid;
		}
		
		return $labelid;
	}

	/*
	 *	returns:
	 *	array 	(
	 *				array('itemid')
	 *			)
	 */
	function _getItemsFromLabel($labelid, $itemid = 0)
	{
		$ret = array();
		
		$query = "SELECT i.itemid FROM ".$this->getTableItemLabel()." i WHERE i.labelid = ".$labelid." ";
		
		if($itemid > 0)
		{
			$query .= " AND i.itemid = ".$itemid." ";
		}
	
		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array('itemid'    => $o->itemid));
			}
		}
		else
		{
			return false;
		}
	
		return $ret;
	}
	
	/*
    * @returns
    *      array(
    *         array(
    *            'labelid', 'labelname', 'blogid', 'labelorder', 'labelmark'
    *         )
    *      )
	*/
	function _getLabelsFromItem($itemid, $order = false)
	{
		$ret = array();
		
		$query = "SELECT l.labelid, l.labelname, l.blogid, i.labelorder, i.labelmark "
				."FROM ".$this->getTableItemLabel()." i, ".$this->getTableLabel()." l "
				."WHERE i.labelid = l.labelid "
				."AND i.itemid = ".$itemid." ";
		
		if($order)
		{
			$query .= "ORDER BY i.labelorder ";
		}
		
		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'labelid'		=> $o->labelid,
					'labelname'		=> $o->labelname,
					'blogid'		=> $o->blogid,
					'labelorder'	=> $o->labelorder,
					'labelmark'		=> $o->labelmark
					));
			}
		}
		else
		{
			return false;
		}
	
		return $ret;
	}

		/*
	 *	returns:
	 *	array 	(
	 *				array('itemid')
	 *			)
	 */
	function _getItemsInCategory($catid)
	{
		$ret = array();
		
		$query = "SELECT inumber as itemid FROM ".sql_table('item')." WHERE icat= ".$catid;
		
		$res = sql_query($query);

		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'itemid'    => $o->itemid
					));
			}
		}
		else
		{
			return false;
		}
	
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Plugin Upgrade handling functions
	function getCurrentDataVersion()
	{
		$currentdataversion = $this->getOption('currentdataversion');
		
		if(!$currentdataversion)
		{
			$currentdataversion = 0;
		}
		
		return $currentdataversion;
	}

	function setCurrentDataVersion($currentdataversion)
	{
		$res = $this->setOption('currentdataversion', $currentdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getCommitDataVersion()
	{
		$commitdataversion = $this->getOption('commitdataversion');
		
		if(!$commitdataversion)
		{
			$commitdataversion = 0;
		}

		return $commitdataversion;
	}

	function setCommitDataVersion($commitdataversion)
	{	
		$res = $this->setOption('commitdataversion', $commitdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getDataVersion()
	{
		return 1;
	}
	
	function upgradeDataTest($fromdataversion, $todataversion)
	{
		// returns true if rollback will be possible after upgrade
		$res = true;
				
		return $res;
	}
	
	function upgradeDataPerform($fromdataversion, $todataversion)
	{
		// Returns true if upgrade was successfull
		
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$this->createOption('del_uninstall', 'Delete NP_LMLabels data tables on uninstall?', 'yesno','no');
					$this->createOption('currentdataversion', 'currentdataversion', 'text','0', 'access=hidden');
					$this->createOption('commitdataversion', 'commitdataversion', 'text','0', 'access=hidden');

					$this->createOption('globalitemurllabel','Include marked labels in URLs for items? (Requires NP_LMFancierURL)', 'yesno', 'no');
					$this->createBlogOption('blogitemurllabel','Include marked labels in URLs for items? (Requires NP_LMFancierURL)', 'select', 'global', 'Use Global|global|Yes|yes|No|no');
					
					$this->createOption('itemtemplate', 'Item Labels', 'textarea', '<%header%><%body%><%none%><%footer%>');
					$this->createOption('itemtemplateheader', 'Item Labels Header', 'textarea', 'Labels: ');
					$this->createOption('itemtemplatebody', 'Item Labels Body', 'textarea', '<a href="<%contexturl%>" title="Add label to filter: <%labelname%>"><%labelname%></a>, ');
					$this->createOption('itemtemplatebodylast', 'Item Labels Body Last', 'textarea', '<a href="<%contexturl%>" title="Add label to filter: <%labelname%>"><%labelname%></a>.');
					$this->createOption('itemtemplatenone', 'Item Labels None', 'textarea', 'None.');
					$this->createOption('itemtemplatefooter', 'Item Labels Footer', 'textarea', '');

					$this->createOption('curtemplate', 'Current Labels', 'textarea', '<%header%><%body%><%clear%><%none%><%footer%>');
					$this->createOption('curtemplateheader', 'Current Labels Header', 'textarea', 'Filtered by labels: ');
					$this->createOption('curtemplatebody', 'Current Labels Body', 'textarea', '<a href="<%contexturl%>" title="Remove label from filter: <%labelname%>"><%labelname%></a>, ');
					$this->createOption('curtemplatebodylast', 'Current Labels Body Last', 'textarea', '<a href="<%contexturl%>" title="Remove label from filter: <%labelname%>"><%labelname%></a>.');
					$this->createOption('curtemplateclear', 'Current Labels Clear', 'textarea', ' <a href="<%contexturl%>" title="Clear all labels from filter">Clear all</a>.');
					$this->createOption('curtemplatenone', 'Current Labels None', 'textarea', 'None.');
					$this->createOption('curtemplatefooter', 'Current Labels Footer', 'textarea', '');

					$this->createOption('clotemplate', 'Cloud Labels', 'textarea', '<%header%><%body%><%none%><%footer%>');
					$this->createOption('clotemplateheader', 'Cloud Labels Header', 'textarea', 'Available labels: ');
					$this->createOption('clotemplatebody', 'Cloud Labels Body', 'textarea', '<a href="<%contexturl%>" class="lmlabelscloud<%size%>" title="Add label to filter: <%labelname%>"><%labelname%> (<%count%>)</a>, ');
					$this->createOption('clotemplatebodylast', 'Cloud Labels Body Last', 'textarea', '<a href="<%contexturl%>" class="lmlabelscloud<%size%>" title="Add label to filter: <%labelname%>"><%labelname%> (<%count%>)</a>.');
					$this->createOption('clotemplatenone', 'Cloud Labels None', 'textarea', 'None.');
					$this->createOption('clotemplatefooter', 'Cloud Labels Footer', 'textarea', '');
					
					$this->createTableLabel();
					$this->createTableItemLabel();
					$res = true;
					break;
				case 2:
					$res = true;
					break;
				case 3:
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		
		return true;
	}
	
	function upgradeDataRollback($fromdataversion, $todataversion)
	{
		// Returns true if rollback was successfull
		for($ver = $fromdataversion; $ver >= $todataversion; $ver--)
		{
			switch($ver)
			{
				case 1:
				case 2:
				case 3:
					$res = true;
					break;
				
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function upgradeDataCommit($fromdataversion, $todataversion)
	{
		// Returns true if commit was successfull
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
				case 2:
				case 3:
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		return true;
	}
	
	function _checkColumnIfExists($table, $column)
	{
		// Retuns: $column: Found, '' (empty string): Not found, false: error
		$found = '';
		
		$res = sql_query("SELECT * FROM ".$table." WHERE 1 = 2");

		if($res)
		{
			$numcolumns = sql_num_fields($res);

			for($offset = 0; $offset < $numcolumns && !$found; $offset++)
			{
				if(sql_field_name($res, $offset) == $column)
				{
					$found = $column;
				}
			}
		}
		
		return $found;
	}
	
	function _addColumnIfNotExists($table, $column, $columnattributes)
	{
		$found = $this->_checkColumnIfExists($table, $column);
		
		if($found === false) 
		{
			return false;
		}
		
		if(!$found)
		{
			$res = sql_query("ALTER TABLE ".$table." ADD ".$column." ".$columnattributes);

			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function _dropColumnIfExists($table, $column)
	{
		$found = $this->_checkColumnIfExists($table, $column);
		
		if($found === false) 
		{
			return false;
		}
		
		if($found)
		{
			$res = sql_query("ALTER TABLE ".$table." DROP COLUMN ".$column);

			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function _needURLPartsSourceVersion()
	{
		return '1.1.1';
	}
	
	function _checkURLPartsSourceVersion()
	{
		$urlPartsVersion = $this->_needURLPartsSourceVersion();
		$aVersion = explode('.', $urlPartsVersion);
		$needmajor = $aVersion['0']; $needminor = $aVersion['1']; $needpatch = $aVersion['2'];
		
		$urlPartsVersion = $this->_getURLPartPlugin()->getVersion();
		$aVersion = explode('.', $urlPartsVersion);
		$major = $aVersion['0']; $minor = $aVersion['1']; $patch = $aVersion['2'];
		
		if($major < $needmajor || (($major == $needmajor) && ($minor < $needminor)) || (($major == $needmajor) && ($minor == $needminor) && ($patch < $needpatch)))
		{
			return false;
		}

		return true;
	}

	function _needFancierURLSourceVersion()
	{
		return '3.0.0';
	}
	
	function _checkFancierURLSourceVersion()
	{
		$fancierURLVersion = $this->_needFancierURLSourceVersion();
		$aVersion = explode('.', $fancierURLVersion);
		$needmajor = $aVersion['0']; $needminor = $aVersion['1']; $needpatch = $aVersion['2'];
		
		$fancierURLVersion = $this->_getFancierURLPlugin()->getVersion();
		$aVersion = explode('.', $fancierURLVersion);
		$major = $aVersion['0']; $minor = $aVersion['1']; $patch = $aVersion['2'];
		
		if($major < $needmajor || (($major == $needmajor) && ($minor < $needminor)) || (($major == $needmajor) && ($minor == $needminor) && ($patch < $needpatch)))
		{
			return false;
		}

		return true;
	}

	function _needReplacementVarsSourceVersion()
	{
		return '1.0.0';
	}
	
	function _checkReplacementVarsSourceVersion()
	{
		$replacementVarsVersion = $this->_needReplacementVarsSourceVersion();
		$aVersion = explode('.', $replacementVarsVersion);
		$needmajor = $aVersion['0']; $needminor = $aVersion['1']; $needpatch = $aVersion['2'];
		
		$replacementVarsVersion = $this->_getReplacementVarsPlugin()->getVersion();
		$aVersion = explode('.', $replacementVarsVersion);
		$major = $aVersion['0']; $minor = $aVersion['1']; $patch = $aVersion['2'];
		
		if($major < $needmajor || (($major == $needmajor) && ($minor < $needminor)) || (($major == $needmajor) && ($minor == $needminor) && ($patch < $needpatch)))
		{
			return false;
		}

		return true;
	}
	
	function _checkURLPartsDataVersion()
	{
		if(!method_exists($this->_getURLPartPlugin(), 'getDataVersion'))
		{
			return false;
		}
		
		$current = $this->_getURLPartPlugin()->getCurrentDataVersion();
		$source = $this->_getURLPartPlugin()->getDataVersion();
		
		if($current < $source)
		{
			return false;
		}

		return true;
	}
}
?>
