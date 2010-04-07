<?php
/**
 * English language file for emaildigest plugin
 * 
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

$lang['enable']             = 'Enable email digest feature';
$lang['enable_admin']       = 'Page/Media changes (conf:notify)';
$lang['enable_subscribers'] = 'Page/Media changes (conf:subscribers)';
$lang['enable_register']    = 'User information changes (conf:registernotify)';

$lang['url_param'] = 'Required URL parameter to send out digests (e.g. requires access to lib/exe/indexer.php?send_email_digest=1)';

$lang['send_wdays']     = 'Day of the week to send out digests';
$lang['send_wdays_sun'] = 'Sunday';
$lang['send_wdays_mon'] = 'Monday';
$lang['send_wdays_tue'] = 'Tuesday';
$lang['send_wdays_wed'] = 'Wednesday';
$lang['send_wdays_thu'] = 'Thursday';
$lang['send_wdays_fri'] = 'Friday';
$lang['send_wdays_sat'] = 'Saturday';

$lang['send_hour'] = 'Time of the day to create digest messages (0-23)';

$lang['link_page']           = 'Link destination of each page item in a message';
$lang['link_page_o_diff']    = 'Difference between pre- and post-revision within the period';
$lang['link_page_o_rev']     = 'Last revision within the period';
$lang['link_page_o_revlist'] = 'List of all revisions';
$lang['link_page_o_current'] = 'Current page';
$lang['link_page_o_none']    = '(No link)';

$lang['link_media']          = 'Link destination of each media file item in a message';
$lang['link_media_o_direct'] = 'Direct link to the media file';
$lang['link_media_o_detail'] = 'Detail page for images, direct link for the others';
$lang['link_media_o_none']   = '(No link)';

$lang['skip']               = 'Changes you want to exclude from digests (this setting is not applied to conf:notify)';
$lang['skip_sub_media']     = 'Media edits';
$lang['skip_sub_minoredit'] = 'Minor edits';
$lang['skip_sub_selfmod']   = 'Edits by the intended recipient him/herself';
$lang['skip_sub_hidden']    = 'Edits on hidden pages (see conf:hidepage)';
$lang['skip_reg_by_admin']  = 'User information changes by the superuser';
$lang['skip_reg_not_reg']   = 'User information changes other than registration';

$lang['test_only'] = 'Enable test-only mode (digests will not be sent out and you will be able to monitor the message body prepared for sending by accessing the <a href="http://www.dokuwiki.org/indexer" class="interwiki iw_doku">debug interface</a>)';
