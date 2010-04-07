<?php
/**
 * Japanese language file for emaildigest plugin
 * 
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

$lang['enable']             = '要約版メールの送信を有効にする処理';
$lang['enable_admin']       = '変更通知 (conf:notify)';
$lang['enable_subscribers'] = '変更通知 (conf:subscribers)';
$lang['enable_register']    = 'ユーザー登録通知 (conf:registernotify)';

$lang['url_param'] = '要約版メールの送信に必要とする URL パラメータ (例: lib/exe/indexer.php?send_email_digest=1 へのアクセスを必要とするなど)';

$lang['send_wdays']     = '要約版メールを送信する曜日';
$lang['send_wdays_sun'] = '日曜日';
$lang['send_wdays_mon'] = '月曜日';
$lang['send_wdays_tue'] = '火曜日';
$lang['send_wdays_wed'] = '水曜日';
$lang['send_wdays_thu'] = '木曜日';
$lang['send_wdays_fri'] = '金曜日';
$lang['send_wdays_sat'] = '土曜日';

$lang['send_hour'] = '要約版メールを作成する時刻 (0-23)';

$lang['link_page']           = 'メール内のページのリンク先';
$lang['link_page_o_diff']    = '対象期間における編集前と編集後の差分ページ';
$lang['link_page_o_rev']     = '対象期間内で最新のリビジョンのページ';
$lang['link_page_o_revlist'] = 'リビジョンの一覧ページ';
$lang['link_page_o_current'] = '最新のページ';
$lang['link_page_o_none']    = 'リンクなし';

$lang['link_media']          = 'メール内のメディアファイルのリンク先';
$lang['link_media_o_direct'] = 'メディアファイルへの直接リンク';
$lang['link_media_o_detail'] = '画像は詳細ページにリンク、その他のファイルは直接リンク';
$lang['link_media_o_none']   = 'リンクなし';

$lang['skip']               = '要約版メールから除く変更内容 (この設定は conf:notify には適用されません)';
$lang['skip_sub_media']     = 'メディアファイルの変更';
$lang['skip_sub_minoredit'] = '軽微な変更';
$lang['skip_sub_selfmod']   = '通知対象のユーザー自身による変更';
$lang['skip_sub_hidden']    = '隠しページへの変更 (conf:hidepage を参照)';
$lang['skip_reg_by_admin']  = 'スーパーユーザーによるユーザー情報の変更';
$lang['skip_reg_not_reg']   = '「登録」以外のユーザー情報の変更';

$lang['test_only'] = 'テストモードを有効にする (メールは送信されなくなり、送信される予定のメールを<a href="http://www.dokuwiki.org/ja:indexer" class="interwiki iw_doku">デバッグインタフェース</a>にアクセスして確認できるようになります)';
