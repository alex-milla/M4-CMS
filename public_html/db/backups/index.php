<?php
// Guard: impide el listado/acceso directo a las copias de seguridad.
http_response_code(403);
exit('Forbidden');
