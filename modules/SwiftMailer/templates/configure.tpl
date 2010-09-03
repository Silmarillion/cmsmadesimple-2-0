{mod_form action='configure' module='ModuleManager'}
{mod_hidden name="name" value=$cms_mapi_module->get_name()}
	<p>
		{mod_label name="host"}{tr}SMTP Host{/tr}{/mod_label}:<br />
		{mod_textbox name="host" value=$cms_mapi_module->Preference->get('host', 'localhost') size="40"}
	</p>
	<input type="submit">
{/mod_form}