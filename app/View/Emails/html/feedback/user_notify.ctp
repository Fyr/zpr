<h4><?=__('Notification from Zuper.su Support Team')?></h4>

<p>
	<?=__('Your feedback with subject "%s" was received by our support team at %s', htmlspecialchars($data['subject']), date('d.m.Y H:i:s', strtotime($data['created'])))?>
</p>
