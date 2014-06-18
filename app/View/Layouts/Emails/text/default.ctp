<?php echo $this->fetch('content');?>

<?=__('With best regards')?>,
<?=$this->Html->link(__('Zuper.su Support Team'), 'mailto:'.Configure::read('support_email'))?>
