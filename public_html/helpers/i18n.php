<?php
/**
 * Bilingual translation system (English/Spanish)
 */

// Only manage session language if session is active
if (session_status() === PHP_SESSION_ACTIVE) {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'es'])) {
        $_SESSION['lang'] = $_GET['lang'];
        // Persistir en DB para visitantes sin sesion
        if (function_exists('saveSetting')) {
            @saveSetting('site_lang', $_GET['lang']);
        }
    } elseif (isset($_POST['lang']) && in_array($_POST['lang'], ['en', 'es'])) {
        $_SESSION['lang'] = $_POST['lang'];
        if (function_exists('saveSetting')) {
            @saveSetting('site_lang', $_POST['lang']);
        }
    } elseif (!isset($_SESSION['lang'])) {
        // Sin sesion: leer idioma de la DB, default 'es'
        if (function_exists('getSetting')) {
            $dbLang = @getSetting('site_lang');
            $_SESSION['lang'] = in_array($dbLang, ['en', 'es']) ? $dbLang : 'es';
        } else {
            $_SESSION['lang'] = 'es';
        }
    }
}

$lang = ($_SESSION['lang'] ?? 'es');

function t($key) {
    global $lang;
    $translations = [
        'nav_login' => ['en' => 'Login', 'es' => 'Entrar'],
        'nav_logout' => ['en' => 'Logout', 'es' => 'Cerrar sesión'],
        'nav_admin' => ['en' => 'Admin', 'es' => 'Admin'],
        'nav_back_to_site' => ['en' => 'Back to site', 'es' => 'Volver al sitio'],
        'nav_back_to_admin' => ['en' => 'Back to admin', 'es' => 'Volver al admin'],
        'nav_go_to_site' => ['en' => 'Go to site', 'es' => 'Ir al sitio'],
        'nav_view_publications' => ['en' => 'Publications', 'es' => 'Publicaciones'],
        'nav_new_post' => ['en' => 'New Post', 'es' => 'Nueva Publicación'],
        'nav_manage_posts' => ['en' => 'Manage Posts', 'es' => 'Gestionar Publicaciones'],
        'nav_settings' => ['en' => 'Settings', 'es' => 'Configuración'],
        'nav_back' => ['en' => 'Back', 'es' => 'Volver'],
        'post_title' => ['en' => 'Title', 'es' => 'Título'],
        'post_content' => ['en' => 'Content', 'es' => 'Contenido'],
        'btn_save_post' => ['en' => 'Save Post', 'es' => 'Guardar Publicación'],
        'btn_update_post' => ['en' => 'Update Post', 'es' => 'Actualizar Publicación'],
        'btn_create_new' => ['en' => 'Create New Post', 'es' => 'Crear Nueva Publicación'],
        'error_required_fields' => ['en' => 'All fields are required.', 'es' => 'Todos los campos son obligatorios.'],
        'error_login_invalid' => ['en' => 'Invalid credentials.', 'es' => 'Usuario o contraseña incorrectos.'],
        'error_login_restricted' => ['en' => 'Restricted Area', 'es' => 'Acceso Restringido'],
        'label_username' => ['en' => 'Username', 'es' => 'Usuario'],
        'label_password' => ['en' => 'Password', 'es' =>'Contraseña'],
        'message_config_saved' => ['en' => 'Configuration saved successfully.', 'es' => 'Configuración guardada correctamente.'],
        'lbl_site_title' => ['en' => 'Site Title', 'es' => 'Título del sitio'],
        'lbl_site_tagline' => ['en' => 'Site Tagline', 'es' => 'Lema del sitio'],
        'label_admin_user' => ['en' => 'Admin Username', 'es' => 'Usuario Admin'],
        'label_new_password' => ['en' => 'New admin password (leave empty to keep current)', 'es' => 'Nueva contraseña admin (dejar vacío para mantener actual)'],
        'btn_save_config' => ['en' => 'Save Configuration', 'es' => 'Guardar Configuración'],
        'lbl_settings' => ['en' => 'Settings', 'es' => 'Configuración'],
        'lbl_system_info' => ['en' => 'System Information', 'es' => 'Información del Sistema'],
        'msg_posts_count' => ['en' => 'Posts in system:', 'es' => 'Publicaciones en el sistema:'],
        'btn_delete_post' => ['en' => 'Delete Post', 'es' => 'Eliminar Publicación'],
        'post_deleted_msg' => ['en' => 'Post deleted successfully.', 'es' => 'Publicación eliminada correctamente.'],
        'post_bulk_deleted_msg' => ['en' => 'All selected posts deleted successfully.', 'es' => 'Todas las publicaciones seleccionadas eliminadas correctamente.'],
        'tbl_col_title' => ['en' => 'Title', 'es' => 'Título'],
        'tbl_col_date' => ['en' => 'Date', 'es' => 'Fecha'],
        'tbl_col_actions' => ['en' => 'Actions', 'es' => 'Acciones'],
        'btn_edit' => ['en' => 'Edit', 'es' => 'Editar'],
        'btn_delete' => ['en' => 'Delete', 'es' => 'Eliminar'],
        'no_posts_msg' => ['en' => 'No publications found.', 'es' => 'No se encontraron publicaciones.'],
        'btn_select_all' => ['en' => 'Select All', 'es' => 'Seleccionar Todo'],
        'btn_delete_selected' => ['en' => 'Delete Selected', 'es' => 'Eliminar Seleccionados'],
        'confirm_delete_post' => ['en' => 'Delete this post?', 'es' => '¿Eliminar esta publicación?'],
        'confirm_bulk_delete' => ['en' => 'Delete all selected posts?', 'es' => '¿Eliminar todas las publicaciones seleccionadas?'],
        'lbl_options' => ['en' => 'ADMINISTRATION OPTIONS', 'es' => 'OPCIONES DE ADMINISTRACIÓN'],
        'msg_login_prompt' => ['en' => 'Please log in to manage publications.', 'es' => 'Por favor inicie sesión para gestionar publicaciones.'],
        'label_current_theme' => ['en' => 'Current Theme', 'es' => 'Tema activo'],
        'nav_edit_post' => ['en' => 'Edit Post', 'es' => 'Editar Publicación'],
        'err_missing_fields' => ['en' => 'All fields are required.', 'es' => 'Todos los campos son obligatorios.'],
        'field_title_label' => ['en' => 'Title:', 'es' => 'Título:'],
        'field_title_ph' => ['en' => 'Enter post title', 'es' => 'Ingrese el título de la publicación'],
        'field_content_label' => ['en' => 'Content:', 'es' => 'Contenido:'],
        'field_content_ph' => ['en' => 'Enter post content', 'es' => 'Ingrese el contenido de la publicación'],
        'err_user_pass_incorrect' => ['en' => 'User or password incorrect', 'es' => 'Usuario o contraseña incorrectos'],
        'page_login_title' => ['en' => 'Admin Login', 'es' => 'Login Admin'],
        'page_login_h1' => ['en' => 'ADMIN LOGIN', 'es' => 'ADMIN LOGIN'],
        'label_user' => ['en' => 'User:', 'es' => 'Usuario:'],
        'lbl_password' => ['en' => 'Password:', 'es' => 'Contraseña:'],
        'ph_username_ph' => ['en' => 'Enter your username', 'es' => 'Ingrese su nombre de usuario'],
        'ph_password_ph' => ['en' => 'Enter your password', 'es' => 'Ingrese su contraseña'],
        'lbl_theme_sel' => ['en' => 'Theme:', 'es' => 'Tema:'],
        'btn_login_enter' => ['en' => 'Enter', 'es' => 'Entrar'],
        'footer_fosforo' => ['en' => 'Phosphor IBM PS/2', 'es' => 'Fosforo IBM PS/2'],
        'nav_admin_settings' => ['en' => 'Settings', 'es' => 'Configuración'],
        'cfg_posts_in_system' => ['en' => 'Publications in system:', 'es' => 'Publicaciones en el sistema:'],
        'cfg_active_theme' => ['en' => 'Active Theme', 'es' => 'Tema activo'],
        'cfg_config_options' => ['en' => 'Configuration Options', 'es' => 'Opciones de configuración'],
        'cfg_site_data' => ['en' => 'Site Data', 'es' => 'Datos del sitio'],
        'cfg_admin_account' => ['en' => 'Administrator Account', 'es' => 'Cuenta de administrador'],
        'field_admin_user_label' => ['en' => 'Admin User:', 'es' => 'Usuario Admin:'],
        'cfg_new_password' => ['en' => 'New admin password (leave empty to not change):', 'es' => 'Nueva contraseña admin (dejar vacío para no cambiar):'],
        'btn_save_config_submit' => ['en' => 'Save Configuration', 'es' => 'Guardar Configuración'],
        'footer_retro' => ['en' => 'Retro Mode IBM PS/2', 'es' => 'Modo Retro IBM PS/2'],
        'page_manage_posts_title' => ['en' => 'Manage Publications', 'es' => 'Gestionar Publicaciones'],
        'tbl_col_id' => ['en' => 'ID', 'es' => 'ID'],
        'tbl_col_select' => ['en' => 'Select', 'es' => 'Seleccionar'],
        'page_return' => ['en' => 'Return', 'es' => 'Volver'],
        'page_m4_cms_header' => ['en' => 'M4 CMS', 'es' => 'M4 CMS'],
        'nav_admin_panel' => ['en' => 'Admin Panel', 'es' => 'Panel de Administración'],
        'nav_posts' => ['en' => 'Posts', 'es' => 'Publicaciones'],
        'page_index' => ['en' => 'Home', 'es' => 'Inicio'],
        'cfg_site_title_ph' => ['en' => 'Enter site title', 'es' => 'Ingrese el título del sitio'],
        'cfg_site_tagline_ph' => ['en' => 'Enter site tagline', 'es' => 'Ingrese el lema del sitio'],
        'page_recent_posts' => ['en' => 'Recent Posts', 'es' => 'Publicaciones Recientes'],
        'no_posts_yet' => ['en' => 'No posts yet.', 'es' => 'Aún no hay publicaciones.'],
        'footer_cms' => ['en' => '', 'es' => ''],
        'page_delete_title' => ['en' => 'Delete Post', 'es' => 'Eliminar Publicación'],
        'delete_confirm_text' => ['en' => 'Are you sure you want to delete the following post?', 'es' => '¿Estás seguro de que quieres eliminar la siguiente publicación?'],
        'btn_cancel' => ['en' => 'Cancel', 'es' => 'Cancelar'],
        'lbl_lang' => ['en' => 'Language', 'es' => 'Idioma'],
        'admin_index_title' => ['en' => 'Admin', 'es' => 'Admin'],
        'admin_h1' => ['en' => 'ADMINISTRATION', 'es' => 'ADMINISTRACION'],
        'admin_login_prompt' => ['en' => 'System operational. Please log in to access the admin panel.', 'es' => 'Sistema operativo. Inicie sesión para acceder al panel de administración.'],
        'admin_enter' => ['en' => 'ENTER', 'es' => 'ENTRAR'],
        'admin_welcome' => ['en' => 'WELCOME', 'es' => 'BIENVENIDO'],
        'admin_status' => ['en' => 'Status: operational', 'es' => 'Estado: operativo'],
        'admin_panel_title' => ['en' => 'CMS ADMINISTRATION', 'es' => 'ADMINISTRACIÓN DEL CMS'],
        'admin_manage_posts' => ['en' => 'MANAGE POSTS', 'es' => 'GESTIONAR POSTS'],
        'admin_settings' => ['en' => 'SETTINGS', 'es' => 'CONFIGURACIÓN'],
        'admin_logout' => ['en' => 'LOGOUT', 'es' => 'CERRAR SESIÓN'],
        'admin_theme_label' => ['en' => 'Theme', 'es' => 'Tema'],
        'lbl_status' => ['en' => 'Status', 'es' => 'Estado'],
        'lbl_published' => ['en' => 'Published', 'es' => 'Publicado'],
        'lbl_draft' => ['en' => 'Draft', 'es' => 'Borrador'],
        'lbl_slug' => ['en' => 'URL Slug', 'es' => 'URL Slug'],
        'ph_slug' => ['en' => 'auto-generated if empty', 'es' => 'auto-generado si vacío'],
        'lbl_category' => ['en' => 'Category', 'es' => 'Categoría'],
        'ph_category' => ['en' => 'Select category', 'es' => 'Seleccionar categoría'],
        'lbl_tags' => ['en' => 'Tags', 'es' => 'Etiquetas'],
        'ph_tags' => ['en' => 'Comma separated (e.g.: php, seo)', 'es' => 'Separa con comas (p. ej.: php, seo)'],
        'lbl_publish_date' => ['en' => 'Publish date', 'es' => 'Fecha de publicación'],
        'help_publish_date' => ['en' => 'When the post becomes visible. A future date schedules it.', 'es' => 'Cuándo se hace visible el post. Una fecha futura lo programa.'],
        'lbl_scheduled' => ['en' => 'Scheduled', 'es' => 'Programado'],
        'lbl_scheduled_info' => ['en' => 'Scheduled: goes live on', 'es' => 'Programado: se hará visible el'],
        'lbl_no_category' => ['en' => 'No category', 'es' => 'Sin categoría'],
        'lbl_all_categories' => ['en' => 'All categories', 'es' => 'Todas las categorías'],
        'lbl_search' => ['en' => 'Search', 'es' => 'Buscar'],
        'ph_search' => ['en' => 'Search posts...', 'es' => 'Buscar publicaciones...'],
        'btn_search' => ['en' => 'Search', 'es' => 'Buscar'],
        'no_search_results' => ['en' => 'No results found.', 'es' => 'No se encontraron resultados.'],
        'lbl_published_on' => ['en' => 'Published on', 'es' => 'Publicado el'],
        'lbl_back_to_list' => ['en' => 'Back to posts', 'es' => 'Volver a publicaciones'],
        'post_not_found' => ['en' => 'Post not found.', 'es' => 'Publicación no encontrada.'],
        'tbl_col_status' => ['en' => 'Status', 'es' => 'Estado'],
        'tbl_col_category' => ['en' => 'Category', 'es' => 'Categoría'],
        'filter_by_category' => ['en' => 'Filter by category', 'es' => 'Filtrar por categoría'],
        'filter_by_status' => ['en' => 'Filter by status', 'es' => 'Filtrar por estado'],
        'lbl_all' => ['en' => 'All', 'es' => 'Todos'],
        'page_post_title' => ['en' => 'Post', 'es' => 'Publicación'],
        'btn_new_post' => ['en' => 'New Post', 'es' => 'Nueva Publicación'],
        'lbl_page' => ['en' => 'Page', 'es' => 'Página'],
        'lbl_of' => ['en' => 'of', 'es' => 'de'],
        'btn_prev' => ['en' => '&larr; Previous', 'es' => '&larr; Anterior'],
        'btn_next' => ['en' => 'Next &rarr;', 'es' => 'Siguiente &rarr;'],
        'lbl_no_more' => ['en' => 'No more posts.', 'es' => 'No hay más publicaciones.'],
        'page_logs_title' => ['en' => 'Activity Log', 'es' => 'Registro de Actividad'],
        'nav_logs' => ['en' => 'Logs', 'es' => 'Registros'],
        'tbl_col_date' => ['en' => 'Date', 'es' => 'Fecha'],
        'tbl_col_event' => ['en' => 'Event', 'es' => 'Evento'],
        'tbl_col_detail' => ['en' => 'Detail', 'es' => 'Detalle'],
        'tbl_col_ip' => ['en' => 'IP', 'es' => 'IP'],
        'log_login_success' => ['en' => 'Login success', 'es' => 'Login correcto'],
        'log_login_fail' => ['en' => 'Login failed', 'es' => 'Login fallido'],
        'log_settings_saved' => ['en' => 'Settings saved', 'es' => 'Configuración guardada'],
        'log_post_created' => ['en' => 'Post created', 'es' => 'Publicación creada'],
        'log_post_updated' => ['en' => 'Post updated', 'es' => 'Publicación actualizada'],
        'log_post_deleted' => ['en' => 'Post deleted', 'es' => 'Publicación eliminada'],
        'no_logs' => ['en' => 'No log entries.', 'es' => 'Sin registros.'],
        'cfg_admin_path_label' => ['en' => 'Admin panel path', 'es' => 'Ruta del panel de administración'],
        'cfg_admin_path_help' => ['en' => 'Custom URL to access the admin panel, like WPS Hide Login in WordPress. Lowercase letters, numbers and dashes only (2-30 chars). The old URL stops working after the change; if you forget the new one, you can see the folder name via FTP or the admin_path key in the DB.', 'es' => 'URL personalizada de acceso al panel, como WPS Hide Login en WordPress. Solo minúsculas, números y guiones (2-30 caracteres). La URL antigua deja de funcionar al cambiarla; si olvidas la nueva, puedes ver el nombre de la carpeta por FTP o la clave admin_path en la DB.'],
        'cfg_admin_path_pass' => ['en' => 'Current password (required to change the path)', 'es' => 'Contraseña actual (obligatoria para cambiar la ruta)'],
        'msg_admin_path_changed' => ['en' => 'Admin path changed. From now on, access the panel via the new URL.', 'es' => 'Ruta del panel cambiada. A partir de ahora accede al panel con la nueva URL.'],
        'err_admin_path_format' => ['en' => 'Invalid path: use 2-30 characters, only lowercase letters, numbers and dashes.', 'es' => 'Ruta no válida: usa de 2 a 30 caracteres, solo minúsculas, números y guiones.'],
        'err_admin_path_reserved' => ['en' => 'That path is reserved by the CMS (post, page, feed, sitemap).', 'es' => 'Esa ruta está reservada por el CMS (post, page, feed, sitemap).'],
        'err_admin_path_exists' => ['en' => 'A file or folder with that name already exists in the CMS root.', 'es' => 'Ya existe un archivo o carpeta con ese nombre en la raíz del CMS.'],
        'err_admin_path_password' => ['en' => 'Current password incorrect. The path was not changed.', 'es' => 'Contraseña actual incorrecta. La ruta no se cambió.'],
        'err_admin_path_rename' => ['en' => 'Could not rename the folder (check write permissions). Nothing was changed.', 'es' => 'No se pudo renombrar la carpeta (revisa los permisos de escritura). No se cambió nada.'],
        'log_admin_path_changed' => ['en' => 'Admin path changed', 'es' => 'Ruta de admin cambiada'],
        'log_admin_path_change_fail' => ['en' => 'Admin path change failed', 'es' => 'Fallo al cambiar ruta de admin'],
        'msg_apply_invalid' => ['en' => 'That change request is invalid or has expired. Try again from Settings.', 'es' => 'Esa solicitud de cambio no es válida o ha caducado. Inténtalo de nuevo desde Configuración.'],
        'btn_apply_back' => ['en' => 'Back to settings', 'es' => 'Volver a configuración'],
        'post_admin_actions' => ['en' => 'Manage post', 'es' => 'Administrar publicación'],
        'btn_send_to_draft' => ['en' => 'Send to draft', 'es' => 'Enviar a borrador'],
        'btn_publish_post' => ['en' => 'Publish', 'es' => 'Publicar'],
        'confirm_unpublish_post' => ['en' => 'Send this post to draft? It will no longer be visible on the site.', 'es' => '¿Enviar esta publicación a borrador? Dejará de estar visible en el sitio.'],
        'msg_status_updated' => ['en' => 'Post status updated.', 'es' => 'Estado de la publicación actualizado.'],
        'post_published_msg' => ['en' => 'Post published successfully.', 'es' => 'Publicación publicada correctamente.'],
        'admin_dashboard_title' => ['en' => 'Dashboard', 'es' => 'Panel'],
        'admin_stats_posts' => ['en' => 'Total Posts', 'es' => 'Total Posts'],
        'admin_stats_published' => ['en' => 'Published', 'es' => 'Publicados'],
        'admin_stats_draft' => ['en' => 'Drafts', 'es' => 'Borradores'],
        'admin_stats_scheduled' => ['en' => 'Scheduled', 'es' => 'Programados'],
        'admin_quick_actions' => ['en' => 'Quick Actions', 'es' => 'Acciones rápidas'],

        // --- Sistema de actualizaciones ---
        'nav_update' => ['en' => 'Updates', 'es' => 'Actualizaciones'],
        'admin_system_section' => ['en' => 'System', 'es' => 'Sistema'],
        'update_check_hint' => ['en' => 'Checks the latest GitHub Release and updates the application files.', 'es' => 'Consulta la última Release de GitHub y actualiza los archivos de la aplicación.'],
        'update_repo' => ['en' => 'Repository', 'es' => 'Repositorio'],
        'update_installed_version' => ['en' => 'Installed version', 'es' => 'Versión instalada'],
        'update_latest_release' => ['en' => 'Latest release', 'es' => 'Última versión'],
        'update_published' => ['en' => 'Published', 'es' => 'Publicada'],
        'update_release_notes' => ['en' => 'Release notes', 'es' => 'Notas de la versión'],
        'update_unknown' => ['en' => 'Unknown', 'es' => 'Desconocida'],
        'update_btn_install' => ['en' => 'Check & install latest release', 'es' => 'Buscar e instalar la última versión'],
        'update_btn_force' => ['en' => 'Force reinstall latest', 'es' => 'Reinstalar la última versión'],
        'update_already_latest' => ['en' => 'You are already on the latest version.', 'es' => 'Ya tienes la última versión.'],
        'update_err_no_release' => ['en' => 'No releases found. Publish a release on GitHub first.', 'es' => 'No se encontraron releases. Publica una release en GitHub primero.'],
        'update_preserve_note' => ['en' => 'Your database, config.php, .env and admin_config.php are never overwritten. A code backup is created before every update.', 'es' => 'Tu base de datos, config.php, .env y admin_config.php nunca se sobrescriben. Se crea una copia del código antes de cada actualización.'],
        'update_backups' => ['en' => 'Backups', 'es' => 'Copias de seguridad'],
        'update_no_backups' => ['en' => 'No backups yet.', 'es' => 'Aún no hay copias de seguridad.'],
        'update_tbl_backup' => ['en' => 'Backup', 'es' => 'Copia'],
        'update_tbl_date' => ['en' => 'Date', 'es' => 'Fecha'],
        'update_tbl_actions' => ['en' => 'Actions', 'es' => 'Acciones'],
        'update_btn_restore' => ['en' => 'Restore', 'es' => 'Restaurar'],
        'update_confirm_restore' => ['en' => 'Restore this backup? The current code will be backed up first.', 'es' => '¿Restaurar esta copia? El código actual se respaldará antes.'],
        'update_action_install' => ['en' => 'Update to', 'es' => 'Actualizar a'],
        'update_action_restore' => ['en' => 'Restore backup', 'es' => 'Restaurar copia'],

        // --- Bloques (widgets) ---
        'nav_widgets' => ['en' => 'Blocks', 'es' => 'Bloques'],
        'page_widgets_title' => ['en' => 'Blocks', 'es' => 'Bloques'],
        'lbl_widget_content' => ['en' => 'Content', 'es' => 'Contenido'],
        'ph_widget_content' => ['en' => 'Write text, paste a YouTube/Vimeo URL, or use basic HTML...', 'es' => 'Escribe texto, pega una URL de YouTube/Vimeo o usa HTML básico...'],
        'help_widget_content' => ['en' => 'Auto-detected: a YouTube/Vimeo/Dailymotion/SoundCloud URL is embedded; basic HTML (<b>, <a>, <ul>...) is allowed; plain text keeps line breaks and auto-links URLs.', 'es' => 'Detección automática: una URL de YouTube/Vimeo/Dailymotion/SoundCloud se muestra embebida; se permite HTML básico (<b>, <a>, <ul>...); el texto plano conserva saltos de línea y autoenlaza URLs.'],
        'lbl_widget_position' => ['en' => 'Order (lower goes first)', 'es' => 'Orden (menor primero)'],
        'wdg_module' => ['en' => 'Module', 'es' => 'Módulo'],
        'wdg_module_help' => ['en' => 'Blocks appear on the homepage, above the post list. Disabled by default.', 'es' => 'Los bloques aparecen en la portada, encima del listado de posts. Desactivado por defecto.'],
        'wdg_module_enabled' => ['en' => 'Blocks module is active.', 'es' => 'El módulo de bloques está activo.'],
        'wdg_module_disabled' => ['en' => 'Blocks module is disabled: blocks are not shown on the site.', 'es' => 'El módulo de bloques está desactivado: no se muestran en el sitio.'],
        'btn_widget_enable' => ['en' => 'Activate module', 'es' => 'Activar módulo'],
        'btn_widget_disable' => ['en' => 'Deactivate module', 'es' => 'Desactivar módulo'],
        'btn_widget_new' => ['en' => 'New block', 'es' => 'Nuevo bloque'],
        'btn_widget_save' => ['en' => 'Save block', 'es' => 'Guardar bloque'],
        'no_widgets' => ['en' => 'No blocks yet.', 'es' => 'Aún no hay bloques.'],
        'widget_saved_msg' => ['en' => 'Block saved successfully.', 'es' => 'Bloque guardado correctamente.'],
        'widget_deleted_msg' => ['en' => 'Block deleted successfully.', 'es' => 'Bloque eliminado correctamente.'],
        'widget_status_msg' => ['en' => 'Block status updated.', 'es' => 'Estado del bloque actualizado.'],
        'widget_module_msg' => ['en' => 'Blocks module updated.', 'es' => 'Módulo de bloques actualizado.'],
        'err_widget_required' => ['en' => 'Content is required.', 'es' => 'El contenido es obligatorio.'],
        'confirm_delete_widget' => ['en' => 'Delete this block?', 'es' => '¿Eliminar este bloque?'],
        'log_widget_created' => ['en' => 'Block created', 'es' => 'Bloque creado'],
        'log_widget_updated' => ['en' => 'Block updated', 'es' => 'Bloque actualizado'],
        'log_widget_deleted' => ['en' => 'Block deleted', 'es' => 'Bloque eliminado'],
        'log_widget_module' => ['en' => 'Blocks module toggled', 'es' => 'Módulo de bloques activado/desactivado'],

        // --- Posts anclados ---
        'lbl_pinned' => ['en' => 'Pinned', 'es' => 'Destacados'],
        'pinned_badge' => ['en' => 'Pinned', 'es' => 'Anclado'],
        'btn_pin' => ['en' => 'Pin', 'es' => 'Anclar'],
        'btn_unpin' => ['en' => 'Unpin', 'es' => 'Desanclar'],
        'btn_move_up' => ['en' => 'Move up', 'es' => 'Subir'],
        'btn_move_down' => ['en' => 'Move down', 'es' => 'Bajar'],
        'post_pinned_msg' => ['en' => 'Post pinned successfully.', 'es' => 'Publicación anclada correctamente.'],
        'post_unpinned_msg' => ['en' => 'Post unpinned successfully.', 'es' => 'Publicación desanclada correctamente.'],
        'post_pin_order_msg' => ['en' => 'Pinned order updated.', 'es' => 'Orden de anclados actualizado.'],
        'err_pin_limit' => ['en' => 'You can pin up to 3 posts. Unpin one first.', 'es' => 'Solo puedes anclar hasta 3 publicaciones. Desancla una primero.'],
        'confirm_unpin' => ['en' => 'Unpin this post?', 'es' => '¿Desanclar esta publicación?'],
        'log_post_pinned' => ['en' => 'Post pinned', 'es' => 'Publicación anclada'],
        'log_post_unpinned' => ['en' => 'Post unpinned', 'es' => 'Publicación desanclada'],
        'log_post_pin_reordered' => ['en' => 'Pinned posts reordered', 'es' => 'Anclados reordenados'],

        // --- Seguridad (CSRF / credenciales) ---
        'err_csrf' => ['en' => 'Invalid session token. Reload the page and try again.', 'es' => 'Token de sesión no válido. Recarga la página e inténtalo de nuevo.'],
        'err_creds_password' => ['en' => 'Current password is incorrect. Account changes were not saved.', 'es' => 'La contraseña actual es incorrecta. No se guardaron los cambios de cuenta.'],
        'lbl_admin_current_password' => ['en' => 'Current password (required to change user or password)', 'es' => 'Contraseña actual (obligatoria para cambiar usuario o contraseña)'],
    ];

    return [$translations[$key][$lang] ?? $key, $lang];
}

/**
 * Render the active flag for a language in a select list
 */
function langSelected($languages, $active) {
    $out = [];
    foreach ($languages as $key => $label) {
        $sel = ($key === $active) ? ' selected' : '';
        $out[] = "<option value=\"{$key}\"{$sel}>{$label}</option>";
    }
    return implode("\n", $out);
}

/**
 * Language options array for select helpers
 */
function langOptions() {
    return ['en' => 'English', 'es' => 'Español'];
}
