<?php
// SenTicket Admin for Sentora CP
// Rebuilt By       : TGates
// Original Author  : Diablo925

class module_controller extends ctrl_module
{
	static $ok;
		
    /**
     * The 'worker' methods.
     */
	
	static function doselect()
    {
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
		
		if (isset($formvars['inListTicket']))
		{
			header("location: ./?module=" . $controller->GetCurrentModule() . '&show=ShowTicket');
			exit;
		}
        return true;
    }
	
	static function doread()
    {
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
		// start remove notice
		$sql = "SELECT * FROM x_accounts WHERE ac_id_pk = :uid";
		$sql = $zdbh->prepare($sql);
		$sql->bindParam(':uid', $currentuser['userid']);
		$sql->execute();
		
		while ($row = $sql->fetch())
		{
			$old_notice = $row["ac_notice_tx"];
		}
		
		$new_notice = str_replace('You have a new support ticket reply.<br>', '', $old_notice);
		$new_notice = str_replace('You have a new support ticket.<br>', '', $new_notice);

		$sql = $zdbh->prepare("
			UPDATE x_accounts
			SET ac_notice_tx = :notice
			WHERE ac_id_pk = :uid");
		$sql->bindParam(':notice', $new_notice);
		$sql->bindParam(':uid', $currentuser['userid']);
		$sql->execute();
		// end remove notice
		if (isset($formvars['inRead']))
		{
			header("location: ./?module=" . $controller->GetCurrentModule() . '&show=read&ticket='. $formvars['innumber']. '');
			exit;
		}
		return true;
	}
	
	static function getisListTicket()
    {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        return (isset($urlvars['show'])) && ($urlvars['show'] == "ShowTicket");
    }
	
	static function dosearch()
    {
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
		
		if (isset($formvars['inSearchButton']))
		{
			header("location: ./?module=" . $controller->GetCurrentModule() . '&show=Search&search='. $formvars['insearch']. '');
			exit;
		}
        return true;
    }
	
	static function ExectuteSendTicket($Ticketstatus, $ticketid, $msg)
	{
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail();
		
		$sql_old = "SELECT * FROM x_ticket WHERE st_number = :number AND st_groupid = :uid";
		$sql_old = $zdbh->prepare($sql_old);
		$sql_old->bindParam(':uid', $currentuser['userid']);
		$sql_old->bindParam(':number', $ticketid);
		$sql_old->execute();
		while ($row_old = $sql_old->fetch())
		{
			$oldmsg = $row_old["st_meassge"];
			$userid = $row_old["st_acc"];
		}
		
		$sql = "SELECT * FROM x_accounts WHERE ac_id_pk = :uid";
		$sql = $zdbh->prepare($sql);
        $sql->bindParam(':uid', $userid);
		$sql->execute();
        while ($row = $sql->fetch())
		{
			$email = $row["ac_email_vc"];
			$old_notice = $row["ac_notice_tx"];
		}
		
		$sql = "SELECT * FROM x_profiles WHERE ud_id_pk = :uid";
		$sql = $zdbh->prepare($sql);
        $sql->bindParam(':uid', $currentuser['userid']);
		$sql->execute();
        while ($row = $sql->fetch())
		{
			$username = $row["ud_fullname_vc"];
		}
		
		$mailmsg = $msg;
		$date = date("Y-m-d - H:i:s");
		$msg = "$oldmsg
		--------------------------------
		$date -- $username: $msg";
		
		$sql = $zdbh->prepare("UPDATE x_ticket SET st_meassge = :msg, st_status = :ticketstatus WHERE st_number = :number AND st_groupid = :uid");
		$sql->bindParam(':uid', $currentuser['userid']);
		$sql->bindParam(':number', $ticketid);
		$sql->bindParam(':msg', $msg);
		$sql->bindParam(':ticketstatus', $Ticketstatus);
        $sql->execute();
		
		$emailsubject = "Your case has been updated (".$ticketid.")";
		$emailbody = "Dear, ".$username."\nYour case has been updated\n------------------------\n\n".$mailmsg."\n\nThe ticket status is: ".$Ticketstatus."";
		$phpmailer = new sys_email();
		$phpmailer->Subject = $emailsubject;
		$phpmailer->Body = $emailbody;
		$phpmailer->AddAddress($email);
		$phpmailer->SendEmail();

		// start client notice update
		$notice = 'You have a new support ticket reply.<br>';
        $sql = $zdbh->prepare("
            UPDATE x_accounts
            SET ac_notice_tx = :notice
            WHERE ac_id_pk = :uid");
        $sql->bindParam(':notice', $notice);
        $sql->bindParam(':uid', $userid);
        $sql->execute();
		// end client notice update

		// start remove notice
		$new_notice = str_replace('You have a new support ticket reply.<br>', '', $old_notice);
		$new_notice = str_replace('You have a new support ticket.<br>', '', $new_notice);

        $sql = $zdbh->prepare("
            UPDATE x_accounts
            SET ac_notice_tx = :notice
            WHERE ac_id_pk = :uid");
        $sql->bindParam(':notice', $new_notice);
        $sql->bindParam(':uid', $currentuser['userid']);
        $sql->execute();
		// end remove notice
		
        self::$ok = true;
		return true;
	}
	
	static function getisread()
    {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        return (isset($urlvars['show'])) && ($urlvars['show'] == "read");
    }

	static function ListSelectTicket($uid)
	{
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail();
		$urlvars = $controller->GetAllControllerRequests('URL');
		$ticket = $urlvars['ticket'];
		$sql = "SELECT * FROM x_ticket WHERE st_groupid = :uid AND st_number = :number ORDER BY st_number";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':uid', $currentuser['userid']);
		$numrows->bindParam(':number', $ticket);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0)
		{
            $sql = $zdbh->prepare($sql);
            $sql->bindParam(':uid', $currentuser['userid']);
			$sql->bindParam(':number', $ticket);
            $res = array();
            $sql->execute();
            while ($row = $sql->fetch())
			{
				$msg = nl2br($row['st_meassge']);
                array_push($res, array(
					'Ticket_number' => $row['st_number'],
					'Ticket_domain' => $row['st_domain'],
					'Ticket_subject' => $row['st_subject'],
					'Ticket_msg' => $msg
				));
            }
            return $res;
        }
		else
		{
            return false;
        }
		
	}
	
	static function getTicketstatus()
	{
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail();
		$urlvars = $controller->GetAllControllerRequests('URL');
		$ticket = $urlvars['ticket'];
		$sql = "SELECT * FROM x_ticket WHERE st_groupid = :uid AND st_number = :number";
		$sql = $zdbh->prepare($sql);
		$sql->bindParam(':uid', $currentuser['userid']);
		$sql->bindParam(':number', $ticket);
		$res = array();
		$sql->execute();
		while ($row = $sql->fetch())
		{
			if($row["st_status"] == "Open") { $statusopen = "selected"; }
			if($row["st_status"] == "Re-Opened") { $statusreopen = "selected"; }
			if($row["st_status"] == "Closed") { $statusclose = "selected"; }
			if($row["st_status"] == "Pending") { $statuspending = "selected"; }
			$res = '<option value="Open" '.$statusopen.'>Open</option>
					<option value="Re-Opened" '.$statusreopen.'>Re-Opened</option>
					<option value="Pending" '.$statuspending.'>Pending</option>
					<option value="Closed" '.$statusclose.'>Closed</option>';
		}
		return $res;
	}
	
	static function ListTicket($uid)
    {
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail();
		$sql = "SELECT * FROM x_ticket WHERE st_groupid = :uid";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':uid', $currentuser['userid']);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0)
		{
			$sql = $zdbh->prepare($sql);
            $sql->bindParam(':uid', $currentuser['userid']);
            $res = array();
            $sql->execute();
            while ($row = $sql->fetch())
			{
                array_push($res, array(
					'ticketid' => $row['st_id'],
					'ticketnumber' => $row['st_number'],
					'ticketdomain' => $row['st_domain'],
					'ticketsubject' => $row['st_subject'],
					'ticketstatus' => $row['st_status']
				));
            }
            return $res;
        }
		else
		{
            return false;
        }
	}
	
	static function ListTicketSearch($uid, $ticket)
    {
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail();
		$ticket = "$ticket%";
		$sql = "SELECT * FROM x_ticket WHERE st_number LIKE :ticket AND st_groupid = :uid";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':uid', $currentuser['userid']);
		$numrows->bindParam(':ticket', $ticket);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0)
		{
			$sql = $zdbh->prepare($sql);
            $sql->bindParam(':uid', $currentuser['userid']);
			$sql->bindParam(':ticket', $ticket);
            $res = array();
            $sql->execute();
            while ($row = $sql->fetch())
			{
                array_push($res, array(
					'ticketid' => $row['st_id'],
					'ticketnumber' => $row['st_number'],
					'ticketdomain' => $row['st_domain'],
					'ticketsubject' => $row['st_subject'],
					'ticketstatus' => $row['st_status']
				));
            }
            return $res;
        }
		else
		{
            return false;
        }
	}
	
    /**
     * End 'worker' methods.
     */

    /**
     * Webinterface sudo methods.
     */

	static function getTicket()
    {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        return self::ListSelectTicket($currentuser['userid']);
    }
	
	static function getTicketList()
    {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        return self::ListTicket($currentuser['userid']);
    } 
	
	static function getTicketListSearch()
    {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
		$urlvars = $controller->GetAllControllerRequests('URL');
        return self::ListTicketSearch($currentuser['userid'], $urlvars['search']);
    }
	
	static function dosend()
    {
        global $controller;
        $formvars = $controller->GetAllControllerRequests('FORM');
        if (self::ExectuteSendTicket($formvars['inStatus'], $formvars['innumber'], $formvars['inMessage']));
	}
	
	static function getResult()
    {
		if (self::$ok)
		{
			return ui_sysmessage::shout(ui_language::translate("Ticket Reply Sent!"), "zannounceok");
		}
		return;
    }
	
	static function getisSearch()
    {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        return (isset($urlvars['show'])) && ($urlvars['show'] == "Search");
    }

    static function getCopyright()
	{
        $copyright = '<font face="ariel" size="2">'.ui_module::GetModuleName().' v2.0.0 &copy; 2013-'.date("Y").' Rebuilt by <a target="_blank" href="http://forums.sentora.org/member.php?action=profile&uid=2">TGates</a> for <a target="_blank" href="http://sentora.org">Sentora Control Panel</a> &#8212; Help support future development of this module and donate today!</font>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="DW8QTHWW4FMBY">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" width="70" height="21" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>';
        return $copyright;
    }

    /**
     * Webinterface sudo methods.
     */
}
?>