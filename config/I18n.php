<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists('i18n_get_lang')) {
  function i18n_get_lang(): string {
    $q = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : '';
    if ($q === 'es' || $q === 'en') {
      $_SESSION['lang'] = $q;
    }
    $lang = strtolower((string)($_SESSION['lang'] ?? 'es'));
    return ($lang === 'en') ? 'en' : 'es';
  }
}

if (!function_exists('t')) {
  function t(string $key, array $vars = []): string {
    static $dict = null;

    if ($dict === null) {
      $dict = [
        'es' => [
          'brand_tagline' => 'Locums • Supporting Care You Can Count On',
          'panel' => 'Panel',
          'logout' => 'Salir',
          'doctor_panel' => 'Panel Médico',
          'session' => 'Sesión',
          'id' => 'ID',

          'tab_offers' => 'Ofertas',
          'tab_requests' => 'Mis solicitudes',
          'tab_calendar' => 'Calendario',
          'tab_space' => 'Mi espacio',
          'tab_profile' => 'Perfil',
          'tab_contacts' => 'Contactos',

          'kpi_offers' => 'Ofertas disponibles',
          'kpi_offers_hint' => 'Publicaciones activas',
          'kpi_requests' => 'Mis solicitudes',
          'kpi_requests_hint' => 'Aplicaciones enviadas',
          'kpi_upcoming' => 'Agenda próxima',
          'kpi_upcoming_hint' => 'Eventos planificados',

          'action_required' => 'Acción requerida',
          'must_be_approved' => 'tu cuenta debe ser aprobada por el administrador para poder aplicar a ofertas.',
          'current_status' => 'Estado actual',
          'go_profile' => 'Ir a Perfil',

          'calendar_title' => 'Calendario de agenda',
          'no_events_table' => 'No existe tabla de eventos todavía (doctor_events o medico_events). Crea la tabla para activar la agenda.',
          'agenda_day' => 'Agenda del día',
          'no_events_today' => 'Sin eventos para este día.',
          'edit' => 'Editar',
          'delete' => 'Eliminar',
          'delete_event_confirm' => '¿Eliminar este evento?',
          'edit_event' => 'Editar evento',
          'create_event' => 'Crear evento',
          'date' => 'Fecha',
          'link_offer_optional' => 'Vincular a oferta (opcional)',
          'no_offer' => 'Sin oferta',
          'title' => 'Título',
          'start_time_optional' => 'Hora inicio (opcional)',
          'end_time_optional' => 'Hora fin (opcional)',
          'status' => 'Estado',
          'notes_optional' => 'Notas (opcional)',
          'cancel_edit' => 'Cancelar edición',
          'clear' => 'Limpiar',
          'save' => 'Guardar',
          'calendar_hint' => 'Organiza tu agenda por día y vincula eventos a ofertas (opcional).',

          'planned' => 'Planificado',
          'confirmed' => 'Confirmado',
          'cancelled' => 'Cancelado',
          'completed' => 'Completado',
          'today' => 'Hoy',
        ],
        'en' => [
          'brand_tagline' => 'Locums • Supporting Care You Can Count On',
          'panel' => 'Dashboard',
          'logout' => 'Log out',
          'doctor_panel' => 'Doctor Panel',
          'session' => 'Session',
          'id' => 'ID',

          'tab_offers' => 'Offers',
          'tab_requests' => 'My applications',
          'tab_calendar' => 'Calendar',
          'tab_space' => 'My space',
          'tab_profile' => 'Profile',
          'tab_contacts' => 'Contacts',

          'kpi_offers' => 'Available offers',
          'kpi_offers_hint' => 'Active postings',
          'kpi_requests' => 'My applications',
          'kpi_requests_hint' => 'Submitted applications',
          'kpi_upcoming' => 'Upcoming agenda',
          'kpi_upcoming_hint' => 'Planned events',

          'action_required' => 'Action required',
          'must_be_approved' => 'your account must be approved by the administrator to apply for offers.',
          'current_status' => 'Current status',
          'go_profile' => 'Go to Profile',

          'calendar_title' => 'Agenda calendar',
          'no_events_table' => 'No events table exists yet (doctor_events or medico_events). Create the table to enable the agenda.',
          'agenda_day' => 'Agenda for the day',
          'no_events_today' => 'No events for this day.',
          'edit' => 'Edit',
          'delete' => 'Delete',
          'delete_event_confirm' => 'Delete this event?',
          'edit_event' => 'Edit event',
          'create_event' => 'Create event',
          'date' => 'Date',
          'link_offer_optional' => 'Link to offer (optional)',
          'no_offer' => 'No offer',
          'title' => 'Title',
          'start_time_optional' => 'Start time (optional)',
          'end_time_optional' => 'End time (optional)',
          'status' => 'Status',
          'notes_optional' => 'Notes (optional)',
          'cancel_edit' => 'Cancel editing',
          'clear' => 'Clear',
          'save' => 'Save',
          'calendar_hint' => 'Organize your agenda by day and optionally link events to offers.',

          'planned' => 'Planned',
          'confirmed' => 'Confirmed',
          'cancelled' => 'Cancelled',
          'completed' => 'Completed',
          'today' => 'Today',
        ],
      ];
    }

    $lang = i18n_get_lang();
    $text = $dict[$lang][$key] ?? ($dict['es'][$key] ?? $key);

    foreach ($vars as $k => $v) {
      $text = str_replace('{' . $k . '}', (string)$v, $text);
    }
    return $text;
  }
}