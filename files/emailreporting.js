// There should be a better way to generate this... TODO

const IMAP = 'IMAP';
const AUTH_XOAUTH2 = 'XOAUTH2';
const PROVIDER_MICROSOFT = 'Microsoft';
const PROVIDER_GOOGLE = 'Google';

const mailbox_type = document.getElementById('mailbox_type');
const mailbox_settings_imap = document.getElementById('mailbox_settings_imap');

const auth_method = document.getElementById('auth_method');
const oauth_provider = document.getElementById('oauth_provider');
const mailbox_settings_logininfo = document.getElementById('mailbox_settings_logininfo');
const mailbox_settings_oauth = document.getElementById('mailbox_settings_oauth');
const mailbox_settings_oauth_microsoft = document.getElementById('mailbox_settings_oauth_microsoft');
const mailbox_settings_oauth_google = document.getElementById('mailbox_settings_oauth_google');

function ERP_setVisible(element, visible) {
	element.classList.toggle('hidden', !visible);
}

function ERP_updateFields() {
	const isImap = mailbox_type.value === IMAP;
	const isOAuth = auth_method.value === AUTH_XOAUTH2;
	const isMicrosoft = oauth_provider.value === PROVIDER_MICROSOFT;
	const isGoogle = oauth_provider.value === PROVIDER_GOOGLE;

	ERP_setVisible(mailbox_settings_imap, isImap);
	ERP_setVisible(mailbox_settings_logininfo, !isOAuth);
	ERP_setVisible(mailbox_settings_oauth, isOAuth);
	ERP_setVisible(mailbox_settings_oauth_microsoft, isOAuth && isMicrosoft);
	ERP_setVisible(mailbox_settings_oauth_google, isOAuth && isGoogle);
}

mailbox_type.addEventListener('change', ERP_updateFields);
auth_method.addEventListener('change', ERP_updateFields);
oauth_provider.addEventListener('change', ERP_updateFields);
ERP_updateFields(); // Set correct state on page load
