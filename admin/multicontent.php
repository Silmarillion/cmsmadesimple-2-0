<?php
#CMS - CMS Made Simple
#(c)2004 by Ted Kulp (wishy@users.sf.net)
#This project's homepage is: http://cmsmadesimple.sf.net
#
#This program is free software; you can redistribute it and/or modify
#it under the terms of the GNU General Public License as published by
#the Free Software Foundation; either version 2 of the License, or
#(at your option) any later version.
#
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#You should have received a copy of the GNU General Public License
#along with this program; if not, write to the Free Software
#Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
#$Id$

$CMS_ADMIN_PAGE=1;

require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'cmsms.api.php');

$urlext='?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];


$action = '';
if (isset($_POST['multiaction'])) $action = $_POST['multiaction'];
if (isset($_POST['reorderpages'])) $action = 'reorder';

{
	$tmp = explode('::',$action,2);
	if( $tmp[0] == 'core' )
	{
		$action = $tmp[1];
	}
	else if( $tmp[0] == '-1' )
	{
		redirect("listcontent.php".$urlext.'&message='.lang('no_bulk_performed'));
	}
	else
	{
		// it's a module action.
		$gCms = cmsms();
		if( !isset($gCms->modules[$tmp[0]]) )
		{
			redirect("listcontent.php".$urlext.'&message='.lang('no_bulk_performed'));
		}
		$obj =& $gCms->modules[$tmp[0]]['object'];
		if( !is_object($obj) )
		{
			redirect("listcontent.php".$urlext.'&message='.lang('no_bulk_performed'));
		}

		// strip out the multicontent params
		$tmp2 = array();
		foreach( $_POST as $key => $val )
		{
			if( startswith($key,'multicontent-') )
			{
				$tmp2[] = substr($key,strlen('multicontent-'));
			}
		}
		// populate the REQUEST with a mact
		$_SESSION['cms_passthru'] = array();
		$_SESSION['cms_passthru']['mact'] = implode(',',array($tmp[0],'m1_',$tmp[1]));
		if( count($tmp2) > 0 )
		{
			$_SESSION['cms_passthru']['m1_contentlist'] = implode(',',$tmp2);
		}
		// handle any normal module action.
		redirect("moduleinterface.php".$urlext);
	}
}

//
// handling core actions from this point forward.
//

check_login();
$thisurl=basename(__FILE__).$urlext;
$userid = get_userid();
include_once("header.php");

if (isset($_POST['reorderpages'])) $action = 'reorder';

if (isset($_POST['cancel']))
  redirect('listcontent.php'.$urlext);

global $gCms;
$db =& $gCms->GetDb();
$hm =& $gCms->GetHierarchyManager();

$nodelist = array();
$bulk = false;

$mypages = author_pages($userid);

function get_delete_list($sel_nodes,&$parent,&$final_result,$depth = 0)
{
	// get the list of items we should delete
	$userid = get_userid();
	if( !check_permission($userid,'Remove Pages') ) return FALSE;
	global $mypages;

	$status = TRUE;
	foreach( $sel_nodes as $node )
	{
		if( check_ownership($userid, $node->getTag()) || quick_check_authorship($node->getTag(), $mypages) )
		{
			$content =& $node->GetContent(false);

			$children =& $node->getChildren(false,true);
			$child_status = array();
			if( isset($children) && count($children) )
			{
				// we have children.. but we may not have access to 
				// any or all of them.
				$tmp = array();
				$child_status = get_delete_list($children,$node,$tmp,$depth+1);
				if( $child_status === FALSE || count($tmp) == 0 )
				{
					// there are children, but for one reason or another
					// we can't delete em. which means we can't delete this
					// parent either, or any of its parents.
					$status = FALSE;
				}
				else
				{
					$final_result[] = $content;
				}

				if( count($tmp) )
				{
					// there are children se can delete.
					for( $i = 0; $i < count($tmp); $i++ )
					{
						$one =& $tmp[$i];
						$final_result[] =& $one;
					}
				}
			}
			else
			{
				// no children
				$final_result[] = $content;
			}
		}
		else
		{
			$status = FALSE;
		}
	}

	return $status;
}

function DoContent(&$list, &$node, $checkdefault = true, $checkchildren = true)
{
	$content =& $node->GetContent(true);
	if (isset($content))
	{
		if (!$checkdefault || ($checkdefault && !$content->DefaultContent()))
		{
			$inarray = false;
			foreach ($list as $oneitem)
			{
				if ($oneitem == $content)
				{
					$inarray = true;
					break;
				}
			}

			if (!$inarray)
				$list[] =& $content;

			if ($checkchildren)
			{
				$children =& $node->getChildren();
				if (isset($children) && count($children))
				{
					reset($children);
					while (list($key) = each($children))
					{
						$onechild =& $children[$key];
						DoContent($list, $onechild, $checkdefault, $checkchildren);
					}
				}
			}
		}
	}
}

$reorder_error = FALSE;
if (isset($_POST['idlist']))
{
	foreach (explode(':', $_POST['idlist']) as $id)
	{
		$node = CmsPageTree::get_instance()->get_node_by_id($id);
		if (isset($node))
		{
			$nodelist[] = $node;
		}
	}
}
else if (isset($_POST['selectedvals']))
{
	foreach (explode(" ", $_POST['selectedvals']) as $id)
	{
		$id = intval(str_replace('phtml_', '', $id));
		$node = CmsPageTree::get_instance()->get_node_by_id($id);
		if (isset($node))
		{
			$nodelist[] = $node;
		}
	}
}
else
{
	foreach ($_POST as $k=>$v)
	{
		if (startswith($k, 'multicontent-'))
		{
			$id = substr($k, strlen('multicontent-'));
			$node =& $hm->sureGetNodeById($id);
			if (isset($node))
			{
				if ($action == 'inactive')
					DoContent($nodelist, $node, true, false);
				else if ($action == 'active' || $action == 'settemplate' || $action == 'setcachable' || $action == 'setnoncachable'
					|| $action == 'showinmenu' || $action == 'hidefrommenu')
					DoContent($nodelist, $node, false, false);
				else if ($action == 'delete')
				{
					$nodelist[] =& $node;
					//DoContent($nodelist, $node, false, true);
				}
			}
		}
		if ($action == 'reorder')
		{	  
			if (startswith($k, 'order-'))
			{
				$id = substr($k, strlen('order-'));
				$node =& $hm->sureGetNodeById($id);
				$one =& $node->getContent(true);
				$parentNode = &$node->getParentNode();
				if (FALSE == empty($reorder_parents_array[$one->ParentId()]) && array_key_exists($v, $reorder_parents_array[$one->ParentId()]))
				{
					$reorder_error = 'sibling_duplicate_order';
				}
				else if ($v > count($parentNode->getChildren()))
				{
					$reorder_error = 'order_too_large';
				}
				else if (0 == $v)
				{
					$reorder_error = 'order_too_small';
				}
				else
				{
					$reorder_array[$id] = $v;
					$reorder_parents_array[$one->ParentId()][$v] = $v;
				}
			}
		}
	}

	if ($action == 'delete' )
	{
		$parent = NULL;
		$result = array();
		$status = get_delete_list($nodelist,$parent,$result);
		$nodelist = $result;
	}

	if ($action == 'reorder' && $reorder_error == FALSE)
	{
		$order_changed = FALSE;
		foreach ($reorder_array AS $page_id => $page_order)
		{
			$node =& $hm->sureGetNodeById($page_id);
			$one =& $node->getContent(true);
			// Only update if order has changed.
			if ($one->ItemOrder() != $page_order)
			{
				$order_changed = TRUE;
				$query = 'UPDATE '.cms_db_prefix().'content SET item_order ='.intval($page_order).' WHERE content_id = ?';
				$db->Execute($query, array($page_id));
			}
		}
		if (TRUE == $order_changed)
		{
			CmsPage::set_all_hierarchy_positions();
		}
		else
		{
			$reorder_error = 'no_orders_changed';
		}
	}
}

if (count($nodelist) == 0 && 'reorder' != $action)
{
	redirect("listcontent.php".$urlext.'&message='.lang('no_bulk_performed'));
}
else
{
	if ($action == 'delete')
	{
		echo '<div class="pagecontainer"><p class="pageheader">'.lang('deletecontent').'</p><br/>';
		$userid = get_userid();
		$access = (check_permission($userid, 'Remove Pages'));
		if ($access)
		{
			echo '<form method="post" action="multicontent.php">' . "\n";
			echo '<div>
				<input type="hidden" name="'.CMS_SECURE_PARAM_NAME.'" value="'.$_SESSION[CMS_USER_KEY].'" />
				</div>';

			echo '<p>'.lang('deletepages').'&nbsp;<em>('.lang('info_deletepages').')</em></p><p>' . "\n";
			$idlist = array();
			foreach ($nodelist as $node)
			{
				if (!is_object($node)) continue;
				if ($node->DefaultContent())
				{
					redirect('listcontent.php'.$urlext.'&error='.lang('error_delete_default_parent'));
				}

				echo $node->name . ' (' . $node->friendly_hierarchy() . ')' . '<br />' . "\n";
				$idlist[] = $node->id;
			}
			echo '</p>';

			echo '<div class="pageoverflow">
				<p class="pagetext">&nbsp;</p>
				<p class="pageinput">';

			echo '<input type="hidden" name="multiaction" value="dodelete" /><input type="hidden" name="idlist" value="'.implode(':', $idlist).'" />' . "\n";
			?>
				<input type="submit" accesskey="s" name="confirm" value="<?php echo lang('submit') ?>"  class="pagebutton" onmouseover="this.className='pagebuttonhover'" onmouseout="this.className='pagebutton'" />
			<input type="submit" accesskey="c" name="cancel" value="<?php echo lang('cancel') ?>" class="pagebutton" onmouseover="this.className='pagebuttonhover'" onmouseout="this.className='pagebutton'" />
			</p>
				</div>
				</form>
				</div>

				<?php
		}
		else
		{
			redirect('listcontent.php'.$urlext.'&error='.lang('no_permission'));
		}
	}
	else if ($action == 'settemplate')
	{
		$pages = array();
		$idlist = array();
		foreach( $nodelist as $one )
		{
			$pages[] = array('name' => $one->name, 'hierarchy' => $one->friendly_hierarchy());
			$idlist[] = $one->id;
		}
		$templateops =& $gCms->GetTemplateOperations();
		$smarty->assign('input_template',$templateops->TemplateDropdown());
		$smarty->assign('pages',$pages);
		$smarty->assign('idlist',$idlist);
		$smarty->assign('text_settemplate',lang('text_settemplate'));
		$smarty->assign('text_pages',lang('pages'));
		$smarty->assign('text_template',lang('template'));
		$smarty->assign('text_submit',lang('submit'));
		$smarty->assign('text_cancel',lang('cancel'));
		$smarty->assign('formurl',$thisurl);
		echo $smarty->fetch('multicontent_template.tpl');
	}
	else if ($action == 'dosettemplate')
	{
		$template_id = (int)$_POST['template_id'];
		$modifyall = check_permission($userid, 'Modify Any Page');
		foreach( $nodelist as $node )
		{
			$permission = ($modifyall || check_ownership($userid, $node->id) || 
				check_authorship($userid, $node->id) || 
				check_permission($userid, 'Manage All Content'));

			if( $permission )
			{
				$node->SetTemplateId($template_id);
				$node->Save();
			}
		}
		redirect('listcontent.php'.$urlext.'&message='.lang('bulk_success'));
	}
	else if ($action == 'dodelete')
	{
		$userid = get_userid();
		$access = check_permission($userid, 'Remove Pages' || check_persmission($userid, 'Manage All Content'));
		if ($access)
		{
			global $gCms;
			$db = $gCms->GetDb();
			foreach (array_reverse($nodelist) as $node)
			{
				$id = $node->id;
				$title = $node->Name() . ' (' . $node->friendly_hierarchy() . ') -- ' . $id;

				$childcount = 0;
				$parentid = -1;

				#Hack alert...  hierarchy manager doesn't take into account for deleted nodes
				$query = 'SELECT parent_id FROM '.cms_db_prefix().'content WHERE content_id = '.$db->qstr($id);
				$result = &$db->Execute($query);

				while ($result && !$result->EOF)
				{
					$parentid = $result->fields['parent_id'];
					$result->MoveNext();
				}

				$query = 'SELECT count(*) as thecount FROM '.cms_db_prefix().'content WHERE parent_id = '.$db->qstr($parentid);
				$result = &$db->Execute($query);

				while ($result && !$result->EOF)
				{
					$childcount = $result->fields['thecount'];
					$result->MoveNext();
				}

				$node->delete();

				audit($id, $title, 'Deleted Content');
			}
			CmsPage::set_all_hierarchy_positions();
			$bulk = true;
		}
		//include_once("footer.php");
		if(! $bulk)
		{
			redirect("listcontent.php".$urlext.'&message='.lang('no_bulk_performed'));
		}
		redirect('listcontent.php'.$urlext.'&message='.lang('bulk_success'));
	}
	else if ($action == 'inactive')
	{
		$userid = get_userid();
		$modifyall = check_permission($userid, 'Modify Any Page');

		foreach ($nodelist as $node)
		{
			$permission = ($modifyall || check_ownership($userid, $node->id) || check_authorship($userid, $node->id) || check_persmission($userid, 'Manage All Content'));

			if ($permission)
			{
				if ($node->active)
				{
					$node->active = false;
					$node->save();
					$bulk = true;
				}
			}
		}
		if (!$bulk)
		{
			redirect("listcontent.php".$urlext.'&message='.lang('no_bulk_performed'));
		}
		redirect('listcontent.php'.$urlext.'&message='.lang('bulk_success'));
	}
	else if ($action == 'active')
	{
		$userid = get_userid();
		$modifyall = check_permission($userid, 'Modify Any Page');

		foreach ($nodelist as $node)
		{
			$permission = ($modifyall || check_ownership($userid, $node->id) || check_authorship($userid, $node->id) || check_persmission($userid, 'Manage All Content'));

			if ($permission)
			{
				if (!$node->active)
				{
					$node->active = true;
					$node->save();
					$bulk = true;
				}
			}
		}
		if (!$bulk)
		{
			redirect("listcontent.php".$urlext.'&message='.lang('no_bulk_performed'));
		}
		redirect('listcontent.php'.$urlext.'&message='.lang('bulk_success'));
	}
	else if ($action == 'setcachable' || $action == 'setnoncachable')
	{
		$flag = ($action == 'setcachable')?true:false;

		$userid = get_userid();
		$modifyall = check_permission($userid, 'Modify Any Page');

		foreach ($nodelist as $node)
		{
			$permission = ($modifyall || check_ownership($userid, $node->id) || check_authorship($userid, $node->id) || check_persmission($userid, 'Manage All Content'));

			if ($permission)
			{
				$node->set_cachable($flag);
				$node->save();
			}
		}
		redirect('listcontent.php'.$urlext.'&message='.lang('bulk_success'));
	}
	else if ($action == 'showinmenu' || $action == 'hidefrommenu')
	{
		$flag = ($action == 'showinmenu')?true:false;

		$userid = get_userid();
		$modifyall = check_permission($userid, 'Modify Any Page');

		foreach ($nodelist as $node)
		{
			$permission = ($modifyall || check_ownership($userid, $node->id) || check_authorship($userid, $node->id) || check_persmission($userid, 'Manage All Content'));

			if ($permission)
			{
				$node->set_show_in_menu($flag);
				$node->save();
			}
		}
		redirect('listcontent.php'.$urlext.'&message='.lang('bulk_success'));
	}
	else if ($action == 'reorder')
	{
		if (FALSE == $reorder_error)
		{
			redirect('listcontent.php'.$urlext.'&message='.lang('pages_reordered'));
		}
		else
		{
			redirect('listcontent.php'.$urlext.'&error='.lang($reorder_error));
		}
	}
	else
	{
		redirect('listcontent.php'.$urlext.'&message='.lang('no_bulk_performed'));
	}
}

include_once("footer.php");

# vim:ts=4 sw=4 noet
?>
