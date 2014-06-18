<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
    <title><?=$title_for_layout; ?></title>
    <?=$this->Html->charset();?>
</head>
<body>
    <?=$this->fetch('content'); ?>
    <hr>
	<p>
	    <?=__('With best regards')?>,<br>
	    <?=$this->Html->link(__('Zuper.su Support Team'), 'mailto:'.Configure::read('support_email'))?>
	</p>
</body>
</html>