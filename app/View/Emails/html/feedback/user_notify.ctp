<h4>Notification from FaceThisWorld Support Team</h4>

<p>
    Your feedback with subject "<?= htmlspecialchars($data['subject']); ?>" was received by our support team at <?= date('d.m.Y H:i:s', strtotime($data['created'])); ?>.
</p>

<hr>

<p>
    With best regards,<br>
    <a href="mailto:<?= Configure::read('support_email'); ?>">FaceThisWorld Support Team</a>
</p>