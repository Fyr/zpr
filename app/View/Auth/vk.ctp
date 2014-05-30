<html>
    <head></head>
    <body>
        <script>
            var query = location.href.split('#');
            window.location = '<?= Configure::read('OAuth.vk.redirect_uri'); ?>?' + query[1];
        </script>
    </body>
</html>