<?php
/**
 * Routes configuration
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different urls to chosen controllers and their actions (functions).
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Config
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
/**
 * Here, we are connecting '/' (base path) to controller called 'Pages',
 * its action called 'display', and we pass a param to select the view file
 * to use (in this case, /app/View/Pages/home.ctp)...
 */
Router::connect('/', array('controller' => 'Home',
                           'action'     => 'index'));
/**
 * ...and connect the rest of 'Pages' controller's urls.
 */

Router::connect('/rating/world/:action', array('controller' => 'RatingWorld'));
Router::connect('/rating/world/*',       array('controller' => 'RatingWorld'));

Router::connect('/rating/country/:id/:action', array('controller' => 'RatingCountry'), array('pass' => array('id'), 'id' => '[0-9]+'));
Router::connect('/rating/country/:id/*',       array('controller' => 'RatingCountry'), array('pass' => array('id'), 'id' => '[0-9]+'));

Router::connect('/rating/city/:id/:action', array('controller' => 'RatingCity'), array('pass' => array('id'), 'id' => '[0-9]+'));
Router::connect('/rating/city/:id/*',       array('controller' => 'RatingCity'), array('pass' => array('id'), 'id' => '[0-9]+'));

Router::connect('/users/:user_id/comments',     array('controller' => 'UserComments', 'action' => 'index', '[method]' => 'GET'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));
Router::connect('/users/:user_id/comments/:id', array('controller' => 'UserComments', 'action' => 'view', '[method]' => 'GET'), array('pass' => array('user_id', 'id'), 'user_id' => '[0-9]+', 'id' => '[0-9]+'));
Router::connect('/users/:user_id/comments',     array('controller' => 'UserComments', 'action' => 'add', '[method]' => 'POST'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));
Router::connect('/users/:user_id/comments/:id', array('controller' => 'UserComments', 'action' => 'edit', '[method]' => 'PUT'), array('pass' => array('user_id', 'id'), 'user_id' => '[0-9]+', 'id' => '[0-9]+'));
Router::connect('/users/:user_id/comments/:id', array('controller' => 'UserComments', 'action' => 'delete', '[method]' => 'DELETE'), array('pass' => array('user_id', 'id'), 'user_id' => '[0-9]+', 'id' => '[0-9]+'));
//Router::connect('/users/:user_id/comments/:id', array('controller' => 'UserComments', 'action' => 'update', '[method]' => 'POST'), array('pass' => array('user_id', 'id'), 'user_id' => '[0-9]+', 'id' => '[0-9]+'));

Router::connect('/users/messages',              array('controller' => 'UserMessages', 'action' => 'index', '[method]' => 'GET'));
Router::connect('/users/messages/unread',       array('controller' => 'UserMessages', 'action' => 'unread', '[method]' => 'GET'));
Router::connect('/users/messages/read/:ids',    array('controller' => 'UserMessages', 'action' => 'read', '[method]' => 'PUT'), array('pass' => array('ids'), 'ids' => '[0-9]+(,[0-9]+)*'));
Router::connect('/users/messages/:user_id',     array('controller' => 'UserMessages', 'action' => 'view', '[method]' => 'GET'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));
Router::connect('/users/messages/:user_id',     array('controller' => 'UserMessages', 'action' => 'add', '[method]' => 'POST'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));
Router::connect('/users/messages/:user_id/:id', array('controller' => 'UserMessages', 'action' => 'edit', '[method]' => 'PUT'), array('pass' => array('user_id', 'id'), 'user_id' => '[0-9]+', 'id' => '[0-9]+'));
Router::connect('/users/messages/:user_id',     array('controller' => 'UserMessages', 'action' => 'deleteAll', '[method]' => 'DELETE'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));
Router::connect('/users/messages/:user_id/:id', array('controller' => 'UserMessages', 'action' => 'delete', '[method]' => 'DELETE'), array('pass' => array('user_id', 'id'), 'user_id' => '[0-9]+', 'id' => '[0-9]+'));

Router::connect('/users/:user_id/neighbors/city',    array('controller' => 'Users', 'action' => 'cityNeighbors'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));
Router::connect('/users/:user_id/neighbors/country', array('controller' => 'Users', 'action' => 'countryNeighbors'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));

Router::connect('/users/:user_id/friends',    array('controller' => 'Users', 'action' => 'friends'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));

Router::connect('/users/:user_id/vote',     array('controller' => 'UserVotes', 'action' => 'add', '[method]' => 'POST'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));

Router::connect('/users/:user_id', array('controller' => 'Users', 'action' => 'view', '[method]' => 'GET'), array('pass' => array('user_id'), 'user_id' => '[0-9]+(,[0-9]+)*'));

Router::connect('/users/:user_id/post', array('controller' => 'Users', 'action' => 'post', '[method]' => 'POST'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));

Router::connect('/feedback', array('controller' => 'Feedback', 'action' => 'add', '{method}' => 'POST'));

Router::connect('/rating/credo', array('controller' => 'Credo', 'action' => 'rating', '[method]' => 'GET'));
Router::connect('/credo/:user_id', array('controller' => 'Credo', 'action' => 'view', '[method]' => 'GET'), array('pass' => array('user_id'), 'user_id' => '[0-9]+'));

Router::connect('/users/messages/credo',     array('controller' => 'Credo', 'action' => 'messages', '[method]' => 'GET'));
Router::connect('/users/messages/credo',     array('controller' => 'Credo', 'action' => 'messageAdd', '[method]' => 'POST'));
Router::connect('/users/messages/credo/:id', array('controller' => 'Credo', 'action' => 'messageEdit', '[method]' => 'PUT'), array('pass' => array('id'), 'id' => '[0-9]+'));

Router::resourceMap(array(
    array('action' => 'index',  'method' => 'GET',    'id' => false),
    array('action' => 'view',   'method' => 'GET',    'id' => true),
    array('action' => 'add',    'method' => 'POST',   'id' => false),
    array('action' => 'edit',   'method' => 'PUT',    'id' => true),
    array('action' => 'delete', 'method' => 'DELETE', 'id' => true),
    array('action' => 'update', 'method' => 'POST',   'id' => true)
));

Router::mapResources('Users');
Router::mapResources('Credo');
Router::mapResources('Zen');
Router::mapResources('Auth');
Router::parseExtensions();

/**
 * Load all plugin routes.  See the CakePlugin documentation on
 * how to customize the loading of plugin routes.
 */
CakePlugin::routes();

/**
 * Load the CakePHP default routes. Remove this if you do not want to use
 * the built-in default routes.
 */
require CAKE . 'Config' . DS . 'routes.php';
