{include file="admin/_head.tpl" title="Suivi du temps" plugin_css=['style.css']}

{include file="common/delete_form.tpl"
	legend="Supprimer cette tâche ?"
	warning="Êtes-vous sûr de vouloir supprimer la tâche « %s » ?"|args:$task.label
	info="Les entrées liées à cette tâche ne seront pas supprimées, mais se retrouveront sans aucune tâche associée."}

{include file="admin/_foot.tpl"}