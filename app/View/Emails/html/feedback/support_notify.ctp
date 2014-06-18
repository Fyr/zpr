<h4><?=__('New feedback from user was registered')?></h4>

<table>
    <tr>
        <th><?=__('From')?></th>
        <td><?= htmlspecialchars($data['email']); ?><?= (isset($data['user_id']) ? ' (user id: ' . $data['user_id'] . ')' : ''); ?></td>
    </tr>
    <tr>
        <th><?=__('Created')?></th>
        <td><?=date('d.m.Y H:i:s', strtotime($data['created'])); ?></td>
    </tr>
    <tr>
        <th><?=__('Subject')?></th>
        <td><?=htmlspecialchars($data['subject']); ?></td>
    </tr>
</table>

<p>
    <?=nl2br(htmlspecialchars($data['text'])); ?>
</p>