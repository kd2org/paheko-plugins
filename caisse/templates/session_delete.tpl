{include file="_head.tpl" title="Supprimer une caisse"}

{include file="common/delete_form.tpl"
	legend="Supprimer cette session de caisse ?"
	confirm="Cocher cette case pour supprimer cette session de caisse, toutes les notes et paiements et associés."
	warning="Êtes-vous sûr de vouloir supprimer la session de caisse n°%d ?"|args:$pos_session.id
	alert="Attention, cela supprimera également toutes les notes et paiements associés."
	info="La loi interdit la suppression d'une session de caisse. Ne supprimer que les sessions de test ou vides."}

{include file="_foot.tpl"}