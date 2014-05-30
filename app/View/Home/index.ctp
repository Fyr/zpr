<?= $this->Html->script('jquery-1.8.2.min.js'); ?>

<ul>
	<li>
		<?= $this->Html->link('Настройки', '#', array('onclick' => "\$('#settings').slideToggle()")); ?>
		<div id="settings" style="display: none;">
<?
	echo $this->Form->create('UserDefaults'); // , array('url' => $this->Html->url(array('controller' => 'Home', 'action' => 'saveSettings')))
	echo $this->Form->input('UserDefaults.status', array('label' => 'Статус по умолчанию'));
	echo $this->Form->input('UserDefaults.credo_id', array('label' => 'Кредо по умолчанию', 'options' => $credoOptions));
	echo $this->Form->input('UserDefaults.positive_votes', array('label' => 'Рейтинг по умолчанию', 'style' => 'width: 30%'));
	echo $this->Form->submit('Сохранить');
	echo $this->Form->end();
?>
		</div>
	</li>
    <li><?= $this->Html->link('Получить данные счётчиков', '/counters'); ?></li>
    <li><?= $this->Html->link('Поиск пользователей', '/users?q=s&per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Земляки пользователя по стране', '/users/1/neighbors/country?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Земляки пользователя по городу', '/users/1/neighbors/city?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Друзья пользователя', '/users/1/friends?fb_id=1,2,3&per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('"Самые" пользователи', '/users/most'); ?></li>
    <li><?= $this->Html->link('Зал славы', '/users/winners'); ?></li>
    <li><?= $this->Html->link('Поиск страны', '/countries?q=s&per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Поиск города', '/cities?q=a&per_page=2&page=1&country=RU'); ?></li>

    <br>

    <li><?= $this->Html->link('Информация о пользователе', '/users/1'); ?></li>
    <li><?= $this->Html->link('VK OAuth',
                              'https://oauth.vk.com/authorize'.
                              '?client_id=' . Configure::read('OAuth.vk.app_id') .
                              '&scope=' . Configure::read('OAuth.vk.permissions') .
                              '&redirect_uri=' . Configure::read('OAuth.vk.redirect_uri') .
                              '&response_type=code' .
                              '&v=' . Configure::read('OAuth.vk.version')); ?>
    </li>
    <li><?= $this->Html->link('VK OAuth Test',
                              'https://oauth.vk.com/authorize'.
                              '?client_id=' . Configure::read('OAuth.vk.app_id') .
                              '&scope=' . Configure::read('OAuth.vk.permissions') .
                              '&redirect_uri=' . Configure::read('OAuth.vk.redirect_uri') .
                              '&response_type=token' .
                              '&display=page' .
                              '&v=' . Configure::read('OAuth.vk.version')); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Авторизация',
                                  '/auth',
                                  array(
                                       'method' => 'POST',
                                       'data'   => array(
                                           'service'         => 'fb',
                                           'user_service_id' => '1739003859',
                                           'api_token'       => 'AAAFOtZApoX3IBAIpa40DfwnJ5O2OLfVaHk3wcJIjVIzwVZASCUCIBy3UvUZC6MwTAnb9Voitthy4pMmTbY1ZBm8m73klnM0Or119uvf67jHyRZAMtUgF9',
                                       )
                                  )); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Авторизация VK',
                                  '/auth',
                                  array(
                                       'method' => 'POST',
                                       'data'   => array(
                                           'service'         => 'vk',
                                           'user_service_id' => '10001955',
                                           'api_token'       => 'AAAFOtZApoX3IBAIpa40DfwnJ5O2OLfVaHk3wcJIjVIzwVZASCUCIBy3UvUZC6MwTAnb9Voitthy4pMmTbY1ZBm8m73klnM0Or119uvf67jHyRZAMtUgF9',
                                       )
                                  )); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Обновить пользователя',
                                  '/users/1',
                                  array(
                                       'method' => 'PUT',
                                       'data'   => array(
                                           'user][name'          => 'Igor Stepanov',
                                           'user][status'        => 'Some status',
                                           'user][email'         => 'blah@gmail.com',
                                           'user][credo'         => 'Some credo',
                                           'picture][url'        => 'https://fbcdn-profile-a.akamaihd.net/hprofile-ak-ash3/t5/275272_100001903680558_1106498244_q.jpg',
                                           'picture][rect][x'    => 0,
                                           'picture][rect][y'    => 0,
                                           'picture][rect][x2'   => 180,
                                           'picture][rect][y2'   => 180,
                                           'picture][rect][w'    => 180,
                                           'picture][rect][h'    => 180,
                                       )
                                  )); ?>
    </li>
    <li>
        Удалить пользователя
        <?= $this->Form->create('User',
                                array(
                                     'type'     => 'DELETE',
                                     'url'      => array('controller' => 'users'),
                                     'default'  => false,
                                     'onSubmit' => 'javascript: void(0);',
                                     'id'       => 'DeleteUserForm',
                                )); ?>
        <?= $this->Form->input('id', array('type' => 'text', 'style' => 'width: 100px;', 'label' => false, 'id' => 'DeleteUserId')); ?>
        <?= $this->Form->button('Удалить', array('onClick' => 'DeleteUser();', 'style' => 'padding: 10px;', 'type' => 'button')); ?>
        <?= $this->Form->end(); ?>
        <br>
        <script type="text/javascript">
            function DeleteUser() {
                var id = $('#DeleteUserId').val();

                if (0 + id == 0) {
                    alert('Bad user id');
                    return false;
                }

                $.ajax({
                           url: '<?= $this->Html->url(array('controller' => 'users')); ?>/' + id,
                           type: 'POST',
                           data: $('#DeleteUserForm').serialize(),
                           dataType: 'text',
                           success: function (data) {
                               alert(data);
                           }
                       });

                return false;
            }
        </script>
        <style type="text/css">
            #DeleteUserForm div.input {
                display: inline;
            }
        </style>
    </li>

    <br>

    <li><?= $this->Html->link('Лидеры мира', '/rating/world?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Лидеры мира по странам', '/rating/world/countries?per_page=2&page=1&leaders=1'); ?></li>
    <li><?= $this->Html->link('Лидеры страны', '/rating/country/191?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Лидеры страны по городам', '/rating/country/191/cities?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Лидеры города', '/rating/city/2881048?per_page=2&page=1'); ?></li>

    <br>

    <li><?= $this->Html->link('Получить комментарии', '/users/1/comments?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Получить комментарий', '/users/1/comments/1'); ?></li>
    <li>
        <?= $this->Form->postLink('Добавить комментарий',
                                  '/users/1/comments',
                                  array(
                                       'data' => array(
                                           'text' => 'Test comment',
                                           'type' => 'positive'
                                       )
                                  )); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Добавить ответ на комментарий',
                                  '/users/1/comments',
                                  array(
                                       'data' => array(
                                           'text'      => 'Test comment',
                                           'type'      => 'positive',
                                           'parent_id' => 1
                                       )
                                  )); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Обновить комментарий',
                                  '/users/1/comments/1',
                                  array(
                                       'method' => 'PUT',
                                       'data'   => array(
                                           'text' => 'Test comment (new text2)',
                                           'type' => 'negative'
                                       )
                                  )); ?>
    </li>
    <li>
        Удалить комментарий
        <?= $this->Form->create('UserComment',
                                array(
                                     'type'     => 'DELETE',
                                     'url'      => array('controller' => 'users/1/comments/'),
                                     'default'  => false,
                                     'onSubmit' => 'console.log($(this));',
                                     'id'       => 'DeleteUserCommentForm',
                                )); ?>
        <?= $this->Form->input('id', array('type' => 'text', 'style' => 'width: 100px;', 'label' => false, 'id' => 'DeleteUserCommentId')); ?>
        <?= $this->Form->button('Удалить', array('onClick' => 'DeleteUserComment();', 'style' => 'padding: 10px;', 'type' => 'button')); ?>
        <?= $this->Form->end(); ?>
        <br>
        <script type="text/javascript">
            function DeleteUserComment() {
                var id = $('#DeleteUserCommentId').val();

                if (0 + id == 0) {
                    alert('Bad comment id');
                    return false;
                }

                $.ajax({
                           url: '/users/1/comments/' + id,
                           type: 'POST',
                           data: $('#DeleteUserCommentForm').serialize(),
                           dataType: 'text',
                           success: function (data) {
                               alert(data);
                           }
                       });

                return false;
            }
        </script>
        <style type="text/css">
            #DeleteUserCommentForm div.input {
                display: inline;
            }
        </style>
    </li>

    <br>

    <li><?= $this->Html->link('Получить все диалоги', '/users/messages'); ?></li>
    <li><?= $this->Html->link('Получить диалог', '/users/messages/1?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Получить количество непрочитанных сообщений', '/users/messages/unread'); ?></li>
    <li>
        <?= $this->Form->postLink('Написать сообщение',
                                  '/users/messages/1',
                                  array(
                                       'data' => array(
                                           'text' => 'Test message',
                                       )
                                  )); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Отметить сообщение как прочитанное',
                                  '/users/messages/1/1',
                                  array(
                                       'method' => 'PUT',
                                       'data'   => array(
                                           'status' => 2
                                       )
                                  )); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Удалить диалог',
                                  '/users/messages/1',
                                  array(
                                       'method' => 'DELETE',
                                       'data'   => array(

                                       )
                                  )); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Удалить сообщение',
                                  '/users/messages/1/1',
                                  array(
                                       'method' => 'DELETE',
                                       'data'   => array(

                                       )
                                  )); ?>
    </li>


    <br>

    <li>
        <?= $this->Form->postLink('Проголосовать',
                                  '/users/1/vote',
                                  array(
                                       'data' => array(
                                           'votes' => 1,
                                       )
                                  )); ?>
    </li>
    <li><?= $this->Html->link('Получить список голосовавших', '/userVotes/getVotedList/likes/0'); ?></li>

    <br>

    <li><?= $this->Html->link('Получить зен-сообщения', '/zen?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Получить зен-сообщение', '/zen/1'); ?></li>
    <li>
        <?= $this->Form->postLink('Написать сообщение для зен-экрана',
                                  '/zen',
                                  array(
                                       'data' => array(
                                           'text' => 'Test zen message',
                                       )
                                  )); ?>
    </li>

    <br>

    <li><?= $this->Html->link('Поиск кредо', '/credo?q=te&per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Рейтинг пользователей по кредо', '/credo/1?per_page=2&page=1'); ?></li>
    <li><?= $this->Html->link('Рейтинг кредо', '/rating/credo?per_page=2&page=1'); ?></li>

    <br>

    <li><?= $this->Html->link('Получить чат по кредо', '/users/messages/credo?per_page=2&page=1'); ?></li>
    <li>
        <?= $this->Form->postLink('Написать сообщение в чат по кредо',
                                  '/users/messages/credo',
                                  array(
                                      'data' => array(
                                          'text' => 'Test chat message',
                                      )
                                  )); ?>
    </li>
    <li>
        <?= $this->Form->postLink('Отметить сообщение чата по кредо как прочитанное',
                                  '/users/messages/credo/1',
                                  array(
                                      'method' => 'PUT',
                                      'data'   => array(
                                          'status' => 2
                                      )
                                  )); ?>
    </li>

    <br>

    <li>
        <?=
        $this->Form->postLink('Написать отзыв',
                              '/feedback',
                              array(
                                   'data' => array(
                                       'email'   => 'andyreaper@mail.ru',
                                       'subject' => 'Test feedback subject',
                                       'text'    => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut tempor tortor enim, vestibulum tempus tellus hendrerit condimentum. Cras non tortor ut turpis tincidunt pellentesque. Pellentesque rutrum libero scelerisque, hendrerit eros luctus, euismod velit. Aenean eu libero ut urna sagittis elementum et non ligula. Quisque id quam egestas metus egestas eleifend vel at leo. Vestibulum quis enim posuere, elementum orci id, luctus sapien. Sed in sem non nunc tempus tristique. Suspendisse eget tortor magna. Maecenas varius purus quis augue condimentum, eu pretium erat fringilla. Nam faucibus a dolor eu consequat..

Vivamus ut dui non sem suscipit pretium non ac lorem. Mauris egestas, metus euismod adipiscing blandit, quam risus aliquam augue, ',
                                   )
                              )); ?>
    </li>
    <br>
    <li>
    	<?= $this->Html->link('Получить баланс по юзеру', 'javascript:void(0)', array('onclick' => "\$('#getBalance').slideToggle()")); ?>
		<div id="getBalance" style="display: none;">
<?
	echo $this->Form->create('GetBalance', array('url' => '#')); 
	echo $this->Form->input('user_id', array('label' => 'User ID (0 - текущий)', 'type' => 'text', 'style' => 'width: 30%'));
	echo $this->Form->input('points', array('label' => 'Кол-во ИВ', 'style' => 'width: 30%', 'readonly' => true));
	echo $this->Form->submit('Отправить запрос', array('onclick' => 'showBalance(); return false;'));
	echo $this->Form->end();
?>
<script type="text/javascript">
function showBalance() {
	$.get('/users/' + $('#GetBalanceUserId').val(), null, function(response){
		if (response && response.status) {
			if (response.status == 'success') {
				$('#GetBalancePoints').val(response.data.balance);
			} else {
				alert(data);
			}
		} else {
			alert('Unknown server error!');
		}
	}, 'json');
}
</script>
		</div>
    </li>
    <li>
    	<?= $this->Html->link('Операция с балансом', 'javascript:void(0)', array('onclick' => "\$('#balanceOper').slideToggle()")); ?>
		<div id="balanceOper" style="display: none;">
<?
	echo $this->Form->create('BalanceHistory', array('url' => array('controller' => 'Home', 'action' => 'balanceModify'))); // , array('url' => $this->Html->url(array('controller' => 'Home', 'action' => 'saveSettings')))
	echo $this->Form->input('user_id', array('label' => 'User ID (0 - текущий)', 'type' => 'text', 'style' => 'width: 30%'));
	echo $this->Form->input('oper_type', array('label' => 'Операция', 'options' => $balanceOperOptions));
	echo $this->Form->input('points', array('label' => 'Добавить к балансу кол-во ИВ', 'style' => 'width: 30%'));
	echo $this->Form->input('comment', array('label' => 'Коммент', 'style' => 'width: 30%'));
	echo $this->Form->submit('Сохранить');
	echo $this->Form->end();
?>
		</div>
    </li>
    <li>
    	<?= $this->Html->link('Установка баланса', 'javascript:void(0)', array('onclick' => "\$('#setBalance').slideToggle()")); ?>
		<div id="setBalance" style="display: none;">
			<b>Внимание!</b> Данная функция изменяет баланс юзера не меняя истории операций по балансу.
<?
	echo $this->Form->create('Balance', array('url' => array('controller' => 'Home', 'action' => 'setBalance'))); // , array('url' => $this->Html->url(array('controller' => 'Home', 'action' => 'saveSettings')))
	echo $this->Form->input('user_id', array('label' => 'User ID (0 - текущий)', 'type' => 'text', 'style' => 'width: 30%'));
	echo $this->Form->input('points', array('label' => 'Кол-во ИВ', 'style' => 'width: 30%'));
	echo $this->Form->submit('Сохранить');
	echo $this->Form->end();
?>
		</div>
    </li>
    <br>

    <li>В процессе тестирования</li>
    <li>
        <?=
        $this->Form->postLink('Постинг',
            '/users/1571/post',
            array(
                'method' => 'POST',
                'data'   => array(
                    'text' => 'asd123 asd123 asd123 asd123 asd123 asd123 asd123',
                )
            )); ?>
    </li>

    <br>
    <br>
    <br>

    <li>Старое</li>
    <li><?= $this->Form->postLink('Обновление последнего поста', '/s/users/1/update.json', array('data' => array('post_id' => 1))); ?></li>
</ul>