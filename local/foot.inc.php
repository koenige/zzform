<?php  if (substr($_SERVER['REQUEST_URI'], 0, 5) == '/inc/') exit; ?>
</div>
</body>
</html>
<?php if (isset($verbindung)) mysql_close($verbindung); ?>
