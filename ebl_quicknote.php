<?php

/**
 * EBL Quicknote
 *
 * Internal messaging plugin for Textpattern CMS 4.9+
 */

if (@txpinterface == 'admin') {
    add_privs('eblquicknote', '1');
    register_tab('extensions', 'eblquicknote', 'Messaging');
    register_callback('ebl_quick_note', 'eblquicknote');
    register_callback('ebl_quicknote_head', 'admin_side', 'head_end');
}
function ebl_quick_note_unreadtotal (){
	global $txp_user;
	
	extract(safe_row('user_id','txp_users','name = "'.$txp_user.'"'));

	return getCount('ebl_quicknote_usermap', 'userID = '.$user_id.' AND readstatus = 0',0);
}

function ebl_quicknote_head()
{
    echo '<style>.eblquicknote .newMessage td{font-weight:bold;}</style>';
}

function ebl_quick_note() {

	$step = ps('step');
	$read = (int)ps('read');
	
	$message = (is_callable($step) ) ? $step() : '';
    echo pagetop('Messaging', $message);
    echo '<div class="eblquicknote">';

	echo n.n.t.t.'<script type="text/javascript">
		$(document).ready(function() { // init everything
			$("#newMsgUI,#rcvdMsgUI,#sentMsgUI").hide();
			$("#newMsg").click(function() { 
				$("#sentMsgUI, #rcvdMsgUI").hide("fast");
				$("#newMsgUI").toggle("fast");
				return false;
			});
			$("#rcvdMsg").click(function() {
				$("#newMsgUI, #sentMsgUI").hide("fast");
				$("#rcvdMsgUI").toggle("fast");
				return false;
			});
			$("#sentMsg").click(function() {
				$("#newMsgUI, #rcvdMsgUI").hide("fast");
				$("#sentMsgUI").toggle("fast");
				return false;
			});
			if($("#listRcvd TR").hasClass("newMessage")) {
				$("#rcvdMsgUI").show();
			}
		});
		</script>';
	
	if($read > 0) {
		echo 	'<div style="margin: 20px auto; width: 700px; margin: 20px auto;"><a href="?event=eblquicknote">Return to Mail Folders</a>'.n.
				ebl_quick_note_ReadUI($read).n.
				'</div>'.n;
	} else {
		echo ebl_quick_note_MainUI();
	}
    echo "</div>";
}


function ebl_list_users() {
	global $txp_user;
	$x = 1;
	$out = '';
	
	$rs = safe_rows_start('*, unix_timestamp(last_access) as last_login', 'txp_users', '1 = 1');
	
	if($rs) {
		while ($a = nextRow($rs))
		{
			extract($a);
			if($txp_user != $name) { // you can't send a message to yourself
				$out .= n.t.'<div style="height: 20px; width: 75px; float: left;"><input type="checkbox" name="users[]" value="'.$user_id.'">'.$RealName.'</input></div>';
				if($x % 4 == 0) $out .= '<br/>';
				$x++;
			}
		}
	}
	return $out;
}

function ebl_sendnewmsg () {
	
	global $txp_user, $txpcfg;
	
	include_once txpath.'/lib/classTextile.php';
	
	$textile = new Textile();
	
	extract(safe_row('user_id','txp_users','name = "'.$txp_user.'"')); 
	
	extract(psa(
		array(
			'eblquicknote_subj',
			'eblquicknote_body',
			'users'
		)
	));

	$body = $textile->TextileThis($eblquicknote_body);
	
	$msgID = 	safe_insert(
				 "ebl_quicknote",
				 "subject = '$eblquicknote_subj',
				  content = '$body',
				  author_id = '$user_id',
				  author_name = '$txp_user',
				  date =	now()
				 "
				);
				
	if($msgID) {
		foreach ($users as $user) {
			$result = safe_insert("ebl_quicknote_usermap", "quicknote_id = '$msgID', userID = '$user'");
		}
	}
}

function ebl_listmsgs($show) {
	global $txp_user;

	$received = ''; $deleted ='';
	
	extract(safe_row('user_id','txp_users','name = "'.$txp_user.'"'));
	
	if($show == 'rcvd') {
		$q 	=	" SELECT ebl_quicknote.*,ebl_quicknote_usermap.deleted, ebl_quicknote_usermap.readstatus from ".safe_pfx_j('ebl_quicknote').
				" INNER JOIN ebl_quicknote_usermap ON ebl_quicknote.ID = ebl_quicknote_usermap.quicknote_id ".
				" WHERE userID = $user_id ORDER BY  `ebl_quicknote`.`date` DESC";
	} elseif ($show == 'sent') {
		$q 	=	" SELECT * from ".safe_pfx_j('ebl_quicknote').
				" WHERE author_name = '$txp_user' ORDER BY  `ebl_quicknote`.`date` DESC ";
	}

	$rs = startRows($q,0);

	if ($rs) {
		while ($a = nextRow($rs)) {
			extract($a);

			if((int)$deleted == 0) {
				$user = ($show =='rcvd') ? $author_name : ebl_listrecipients($ID,3);
				
				$received .= n.tr(
						'<td>'.$ID.'</td>'.
						'<td>'.$date.'</td>'.
						'<td>'.$user.'</td>'.
						'<td>'.$subject.'</td>'.
						'<td><a href="?event=eblquicknote&read='.$ID.'">Read</a></td>'.
						'<td>'.dlink('eblquicknote','ebl_delmsg','ID',$ID,'Delete this Message?','type',$show).'</td>',
						($show == 'rcvd') ? ($readstatus == 0) ? ' class="newMessage" ' : '' : ''
						);
			}
		}
	}
	return $received;
}

function ebl_listrecipients($ID,$brNum) {
	
	$q 	 =	'SELECT * FROM '.safe_pfx_j('ebl_quicknote_usermap, txp_users').
			' WHERE ebl_quicknote_usermap.quicknote_id = '.$ID.' AND ebl_quicknote_usermap.userID = txp_users.user_id';
			
	$rs	 =	startRows($q);
	
	$out =''; $x = 0;
	
	if($rs) {
		while($a = nextRow($rs)) {
			if($x >= (int)$brNum) { $br = '<br/>'; $x = 1; } else { $br = ''; $x++; }
			extract($a);
			$out .= $br.$RealName.'; ';
		}
	}
	return $out;
}

function ebl_quicknote_sendMsg($reply = FALSE, $to = null, $subject = null, $textarea = null) {

	$userlist	= (!$reply) ? ebl_list_users() : $to;
	$subject	= ($subject) ? 'Re: '.$subject : '';
	$textarea	= ($textarea) ? '---original message---'.strip_tags($textarea).n.'---' : '';

	$out =	n.n.tag(
			n.form(
				(($reply) ? '' : n.'<fieldset id="eblquicknote_new"><legend><a href="#" id="newMsg"><strong>Send Message</strong></a></legend>' ).
				n.tag(
					n.'<label >Send to : </label>'.$userlist.br.
					n.'<label for="eblquicknote_subj">Subject : </label>'.finput('text','eblquicknote_subj',$subject,'','','','','','eblquicknote_subj').br.
					n.'<label for="eblquicknote_body">Message Body : </label><textarea id="eblquicknote_body" name="eblquicknote_body">'.$textarea.'</textarea>'.br.
					n.hInput('event','eblquicknote').
					n.hInput('step','ebl_sendnewmsg').
					n.'<label for="saveMsg">Send Message : </label>'.fInput('submit','ebl_saveMsg',gTxt('Save'),"publish", '', '', '', '','saveMsg').br,
					'div',
					' id="newMsgUI"').
				(($reply) ? n : n.'</fieldset>'.n)
				),
			'div',
			' style="width: 600px; margin: 0 auto; text-align: left"'
			);
	return $out;
}

function ebl_quick_note_MainUI() {

	$newMessageCount = ebl_quick_note_unreadtotal();
	
	$newMessages = (($newMessageCount) > 0) ? " - [$newMessageCount] New" : ' - 0 New';
	
	$out  =	n.n.tag(
				n.'<fieldset id="eblquicknote_rcvd"><legend><a href="#" id="rcvdMsg"><strong>Received Messages</strong></a>'.$newMessages.'</legend>'.
				n.tag(
					n.n.startTable('listRcvd').
						n.tr(
							n.hCell('#').
							n.hCell(gTxt('Date')).
							n.hCell(gTxt('From')).
							n.hCell(gTxt('Subject')).
							n.hCell('')
							).ebl_listmsgs('rcvd').
						endtable(),
					'div',
					' id="rcvdMsgUI"').
					n.'</fieldset>',
					'div',
					' style="width: 600px; margin: 0 auto; text-align: left"'
					);

	$out .= ebl_quicknote_sendMsg();

	$out .=	tag(
				n.'<fieldset id="eblquicknote_sent"><legend><a href="#" id="sentMsg"><strong>Sent Messages</strong></a></legend>'.
				n.tag(
					n.n.startTable('list').
						n.tr(
							n.hCell('#').
							n.hCell(gTxt('Date')).
							n.hCell(gTxt('To')).
							n.hCell(gTxt('Subject')).
							n.hCell('')
							).ebl_listmsgs('sent').
					endtable(),
				'div',
				' id="sentMsgUI"').
				n.'</fieldset>',
				'div',
				' style="width: 600px; margin: 0 auto; text-align: left"'
				);
				
	return $out;
}

function ebl_quick_note_ReadUI($ID) {

	global $txp_user;

	$user_info = safe_row('*','txp_users','name = "'.$txp_user.'"');

	$rs1 = safe_row(
		'*',
		'ebl_quicknote,ebl_quicknote_usermap, txp_users',
		"ebl_quicknote.ID = ebl_quicknote_usermap.quicknote_id
		AND ebl_quicknote_usermap.userID = txp_users.user_id
		AND ebl_quicknote.ID = $ID
		AND (txp_users.name = '$txp_user' OR ebl_quicknote.author_name ='$txp_user')",0
	);

	if($rs1) {
		extract($rs1);
		
		if($user_info['user_id'] == $author_id) {
			$sentby_txp_user = TRUE;
			$show = 'sent';
		} else {
			$sentby_txp_user = FALSE;
			$show = 'rcvd';
			$rs = safe_update('ebl_quicknote_usermap','readstatus = 1','userID = '.$user_id.' AND quicknote_id = '.$quicknote_id,0);
		}
		
		$del = ($sentby_txp_user) ? 'rcv' : 'snt';
		
		$replyto = t.'<div style="height: 20px; width: 75px; float: left;"><input type="hidden" name="users[]" value="'.$author_id.'">'.$author_name.'</div>';
		
		$out =	'<p><b>Message ID</b> : '.$ID.'</p>'.
				'<p><b>From</b> : '.$author_name.'</p>'.
				'<p><b>To</b> : '.ebl_listrecipients($ID,10).'</p>'.
				'<p><b>Subject</b> : '.$subject.'</p>'.
				'<p><b>Date</b> : '.$date.'</p><hr/>'.
				$content.'<hr/>'.n.
				((!$sentby_txp_user) ? '<div style="margin: 10px 0;"><a href="#" id="newMsg">Reply</a> | ': '').
				dlink('eblquicknote','ebl_delmsg','ID',$ID,'Delete this Message?','type',$show).'</div>'.br;

		$out .= ebl_quicknote_sendMsg(TRUE,$replyto,$subject,$content);

	} else {
		$out = "No message found matching this ID and your username";
	}
	
	return	'<div style="border: 1px solid #EEE;padding: 15px;">'.n.
				$out.n.
			'</div>';	
}

function ebl_delmsg() {
	global $txp_user;
	
	$ID   = ps('ID');
	$type = ps('type');
	
	if($type == 'sent') {
		$rs = @safe_delete('ebl_quicknote','ID = '.$ID);
		$rs2 = @safe_delete('ebl_quicknote_usermap','quicknote_id = '.$ID);
	} elseif ($type == 'rcvd') {
		@extract(safe_row('user_id','txp_users','name = "'.$txp_user.'"'));
		$rs = safe_update('ebl_quicknote_usermap','deleted = 1','userID = '.$user_id.' AND quicknote_id = '.$ID);
	}
	
	$out = ($rs) ? 'Message #'.$ID.' was deleted.' : '<b>Error</b> deleting message #'.$ID;

	return $out;
}

?>