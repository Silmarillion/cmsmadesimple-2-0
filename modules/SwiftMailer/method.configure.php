<?php
#-------------------------------------------------------------------------
# Module: SwiftMailer - a simple wrapper around swift
# Version: 1.0, Ted Kulp <ted@cmsmadesimple.org>
#
#-------------------------------------------------------------------------
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
# Or read it online: http://www.gnu.org/licenses/licenses.html#GPL
#
#-------------------------------------------------------------------------
if (!isset($gCms)) die("Can't call actions directly!");

$valid = false;
if (isset($params['host']))
{
	// step 2, validate, store values, set module as configured
	$this->Preference->set('host',$params['host']);	
	$this->Configuration->set_configured();
	$valid = true;
}

if (!$valid)
{
	// first time through, present a form
	echo $this->Template->process('configure.tpl');
	ob_flush();
}
?>