<?php
/**
 * @version    3.6.x
 * @package    Disqus Comments (for Joomla)
 * @author     JoomlaWorks - http://www.joomlaworks.net
 * @copyright  Copyright (c) 2006 - 2016 JoomlaWorks Ltd. All rights reserved.
 * @license    GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined('_JEXEC') or die ;
if (version_compare(JVERSION, '1.6.0', 'lt'))
{
	jimport('joomla.installer.installer');
	$mainframe = JFactory::getApplication();
	$db = JFactory::getDBO();

	$status = new stdClass;
	$status->plugins = array();

	// Plugins
	$plugins = $this->manifest->getElementByPath('plugins');
	if (is_a($plugins, 'JSimpleXMLElement') && count($plugins->children()))
	{
		foreach ($plugins->children() as $plugin)
		{
			$pname = $plugin->attributes('plugin');
			$pgroup = $plugin->attributes('group');
			$db = JFactory::getDBO();
			$query = 'SELECT `id` FROM #__plugins WHERE element = '.$db->Quote($pname).' AND folder = '.$db->Quote($pgroup);
			$db->setQuery($query);
			$plugins = $db->loadResultArray();
			if (count($plugins))
			{
				foreach ($plugins as $plugin)
				{
					$installer = new JInstaller;
					$result = $installer->uninstall('plugin', $plugin, 0);
				}
			}
			$status->plugins[] = array(
				'name' => $pname,
				'group' => $pgroup,
				'result' => $result
			);
		}
	}
}
?>
<?php if (version_compare(JVERSION, '1.6.0', 'lt')): $rows = 0; ?>
<h2><?php echo JText::_('Removal status'); ?></h2>
<table class="adminlist">
	<thead>
		<tr>
			<th class="title" colspan="2"><?php echo JText::_('Extension'); ?></th>
			<th width="30%"><?php echo JText::_('Status'); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<td colspan="3"></td>
		</tr>
	</tfoot>
	<tbody>
		<tr class="row0">
			<td class="key" colspan="2"><?php echo JText::_('Component'); ?></td>
			<td><strong><?php echo JText::_('Removed'); ?></strong></td>
		</tr>
		<?php if (count($status->plugins)): ?>
		<tr>
			<th><?php echo JText::_('Plugin'); ?></th>
			<th><?php echo JText::_('Group'); ?></th>
			<th></th>
		</tr>
		<?php foreach ($status->plugins as $plugin): ?>
		<tr class="row<?php echo (++ $rows % 2); ?>">
			<td class="key"><?php echo ucfirst($plugin['name']); ?></td>
			<td class="key"><?php echo ucfirst($plugin['group']); ?></td>
			<td><strong><?php echo ($plugin['result']) ? JText::_('Removed') : JText::_('Not removed'); ?></strong></td>
		</tr>
		<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
<?php endif; ?>