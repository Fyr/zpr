<div style="width:600px">
<?php foreach($tests as $test) : ?>
    <b><?= $test['testDescription'] ?></b>
<ul>
    <?php foreach ($test['iteam'] as $resultTest) : ?>
	<li><?= $resultTest['test'] ?>
	    <br />
	<span style="color: <?= $resultTest['assert'] == 'OK' ? 'green' : 'red' ?>">
	<i>Ожидается: <?= $resultTest['expected'] ?>&nbsp;&nbsp;&nbsp;Получено: <?= $resultTest['result'] ?>&nbsp;&nbsp;&nbsp;Результат: <?= $resultTest['assert'] ?></i>
	</span>
	<br /><br />
    </li>

    <?php endforeach; ?>
    </ul>
<?php endforeach; ?>
</div>