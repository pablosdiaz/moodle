<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish strings.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Generador de contenido IA';

// Capabilities.
$string['coursegenerator:generate'] = 'Generar contenido con IA en un curso';
$string['coursegenerator:viewreport'] = 'Ver el informe de generación de contenido con IA';

// Settings.
$string['settings_general'] = 'Configuración del proveedor de IA';
$string['settings_enabled'] = 'Activar la generación de contenido';
$string['settings_enabled_desc'] = 'Si se desactiva, los docentes no podrán generar contenido nuevo, pero el contenido existente y el informe seguirán funcionando.';
$string['settings_apiurl'] = 'Endpoint de la API';
$string['settings_apiurl_desc'] = 'Endpoint compatible con "chat completions", p. ej. https://api.openai.com/v1/chat/completions';
$string['settings_apikey'] = 'Clave de la API';
$string['settings_apikey_desc'] = 'Clave secreta usada para autenticarse ante el proveedor de IA. Manténgala privada.';
$string['settings_model'] = 'Modelo';
$string['settings_model_desc'] = 'Identificador del modelo a usar, p. ej. gpt-4o-mini.';
$string['settings_maxsections'] = 'Máximo de secciones por petición';
$string['settings_maxsections_desc'] = 'Límite superior del número de secciones que un docente puede generar en una sola petición.';
$string['settings_maxquestions'] = 'Máximo de preguntas por petición';
$string['settings_maxquestions_desc'] = 'Límite superior del número de preguntas de cuestionario que un docente puede generar en una sola petición.';

// Admin dashboard.
$string['dashboard'] = 'Informe de generación de contenido';
$string['dashboard_heading'] = 'Generación de contenido con IA';
$string['dashboard_intro'] = 'Resumen del contenido que los docentes han generado con el generador de cursos IA.';
$string['stat_total'] = 'Generaciones totales';
$string['stat_totalitems'] = 'Elementos creados en total';
$string['stat_courses'] = 'Cursos que usan el generador';
$string['stat_teachers'] = 'Docentes activos';
$string['chart_bytype'] = 'Generaciones por tipo de contenido';
$string['chart_overtime'] = 'Generaciones en los últimos 30 días';
$string['table_byteacher'] = 'Contenido generado por docente';
$string['table_bycourse'] = 'Contenido generado por curso';
$string['table_recent'] = 'Actividad más reciente';
$string['col_teacher'] = 'Docente';
$string['col_course'] = 'Curso';
$string['col_type'] = 'Tipo';
$string['col_count'] = 'Generaciones';
$string['col_items'] = 'Elementos creados';
$string['col_topic'] = 'Tema';
$string['col_date'] = 'Fecha';
$string['col_status'] = 'Estado';
$string['status_success'] = 'Correcto';
$string['status_error'] = 'Error';
$string['nodata'] = 'Todavía no se ha generado ningún contenido.';
$string['contenttype_sections'] = 'Secciones';
$string['contenttype_page'] = 'Página';
$string['contenttype_quiz'] = 'Cuestionario';

// Teacher panel.
$string['teacherpanel'] = 'Generar contenido con IA';
$string['teacherpanel_heading'] = 'Generar contenido para {$a}';
$string['teacherpanel_intro'] = 'Describe lo que necesitas y el generador de cursos IA lo creará directamente en tu curso.';
$string['form_contenttype'] = '¿Qué quieres generar?';
$string['form_topic'] = 'Tema / instrucciones';
$string['form_topic_help'] = 'Describe el tema y el nivel de detalle que quieres, p. ej. "Introducción a la fotosíntesis para 4º de ESO".';
$string['form_numsections'] = 'Número de secciones';
$string['form_sectionnum'] = 'Sección donde añadir el contenido';
$string['form_title'] = 'Título';
$string['form_numquestions'] = 'Número de preguntas';
$string['form_submit'] = 'Generar';
$string['form_disabled'] = 'El generador de contenido con IA está actualmente desactivado por el administrador del sitio.';
$string['form_noapikey'] = 'El administrador del sitio todavía no ha configurado una clave de API de IA. Por favor, contacta con él.';
$string['newsection'] = 'Sección nueva (al final del curso)';
$string['recentgenerations'] = 'Tus generaciones recientes en este curso';
$string['success_sections'] = 'Se han creado {$a} sección(es) nueva(s).';
$string['success_page'] = 'Se ha creado la página "{$a}".';
$string['success_quiz'] = 'Se ha creado el cuestionario "{$a->name}" con {$a->count} pregunta(s).';
$string['error_generation'] = 'Ha fallado la generación de contenido: {$a}';
$string['error_aiempty'] = 'El proveedor de IA ha devuelto una respuesta vacía o no válida.';
$string['error_aihttp'] = 'El proveedor de IA ha devuelto un error (HTTP {$a}).';

// Navigation.
$string['navnode'] = 'Generador de contenido IA';

// Privacy.
$string['privacy:metadata:local_coursegenerator_log'] = 'Información sobre las peticiones de generación de contenido con IA realizadas por un docente.';
$string['privacy:metadata:local_coursegenerator_log:userid'] = 'El identificador del usuario que solicitó la generación.';
$string['privacy:metadata:local_coursegenerator_log:courseid'] = 'El curso donde se generó el contenido.';
$string['privacy:metadata:local_coursegenerator_log:contenttype'] = 'El tipo de contenido generado.';
$string['privacy:metadata:local_coursegenerator_log:itemname'] = 'Un resumen legible del contenido generado.';
$string['privacy:metadata:local_coursegenerator_log:topic'] = 'El texto del tema/instrucciones introducido por el docente.';
$string['privacy:metadata:local_coursegenerator_log:timecreated'] = 'El momento en que se solicitó la generación.';
