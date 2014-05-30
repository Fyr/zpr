<?= $this->Html->script('jquery-1.8.2.min.js'); ?>

<ul>
    <li><?= $this->Html->link('Лидеры по странам', '/s/topByCountries.json'); ?></li>
    <li><?= $this->Html->link('Рейтинги по стране', '/s/userRatingsByCountry.json?country=RU&per_page=2&page=1'); ?></li>
    <li><?= $this->Form->postLink('Голосование за пользователя', '/s/users/1/vote.json', array('data' => array('vote' => 1))); ?></li>
    <li><?= $this->Html->link('Поиск пользователей', '/s/users.json?q=s&per_page=2&page=1'); ?></li>
    <li><?= $this->Form->postLink('Авторизация пользователя', '/s/auth.json', array('data' => array('id' => "100003887631676"))); ?></li>
    <li>
        <?= $this->Form->postLink('Настройка профиля пользователя', '/s/users/1/profile.json', array(
            'data' => array(
                'name'    => 'Igor Stepanov',
                'country' => 'BY',
                'picture' => 'https://fbcdn-profile-a.akamaihd.net/hprofile-ak-snc4/174419_1739003859_7807873_n.jpg',
                'h'       => 180,
                'w'       => 180,
                'x'       => 0,
                'x2'      => 180,
                'y'       => 0,
                'y2'      => 180
        ))); ?>
    </li>
    <li><?= $this->Html->link('Информация о пользователе', '/s/users/1.json'); ?></li>
    <li><?= $this->Form->postLink('Обновление последнего поста', '/s/users/1/update.json', array('data' => array('post_id' => 1))); ?></li>
    <li>Удаление пользователя
        <?= $this->Form->create('User', array(
            'type' => 'get',
            'url' => array('controller' => 'home', 'action' => 'deleteUser'),
            'default' => false
        )); ?>
        <?= $this->Form->input('id', array('type' => 'text', 'style' => 'width: 100px;', 'label' => false)); ?>
        <?= $this->Form->end('Удалить'); ?>
    </li>
</ul>

<script type="text/javascript">
    $(document).ready(function() {
        $('#UserIndexForm input[type="submit"]').click(function(){
            form = $(this).closest('form');

            $.ajax({
                url:form.attr('action'),
                type:'GET',
                data:form.serialize(),
                dataType:'text',
                success:function (data) {
                    alert(data);
                }
            });
        });
    });
</script>