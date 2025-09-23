
Voici les modifications pour ajouter une checkbox "Tout sélectionner/désélectionner" avec tous les membres cochés par défaut :
1. Modification du formulaire d'ajout de dépense dans group.php
Remplacez la section des participants dans le formulaire d'ajout par :


<div class="form-group">
                        <label>Participants :</label>
                        <div style="margin-bottom: 0.5rem;">
                            <label style="font-weight: bold; color: #4f46e5;">
                                <input type="checkbox" id="select-all-add" onchange="toggleAllParticipants('add')" checked>
                                Sélectionner tous les membres
                            </label>
                        </div>
                        <div class="checkbox-group">
                            <?php foreach($members as $member): ?>
                                <label>
                                    <input type="checkbox" name="participants[]" value="<?= htmlspecialchars($member['member_name']) ?>" 
                                           class="participant-checkbox-add" onchange="updateSelectAllState('add')" checked>
                                    <?= htmlspecialchars($member['member_name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
					
					
/***************************************************/

2. Modification des formulaires de modification
Dans la section d'affichage des dépenses, remplacez la partie participants du formulaire de modification :
/***************************************************/


<div style="margin-bottom: 1rem;">
                                        <label style="font-weight: 500; margin-bottom: 0.5rem; display: block;">Participants :</label>
                                        <div style="margin-bottom: 0.5rem;">
                                            <label style="font-weight: bold; color: #4f46e5; font-size: 0.9rem;">
                                                <input type="checkbox" id="select-all-edit-<?= $expense['id'] ?>" 
                                                       onchange="toggleAllParticipants('edit-<?= $expense['id'] ?>')" checked>
                                                Sélectionner tous les membres
                                            </label>
                                        </div>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem;">
                                            <?php foreach($members as $member): ?>
                                                <label style="display: flex; align-items: center; font-size: 0.9rem;">
                                                    <input type="checkbox" name="participants[]" value="<?= htmlspecialchars($member['member_name']) ?>"
                                                           class="participant-checkbox-edit-<?= $expense['id'] ?>"
                                                           onchange="updateSelectAllState('edit-<?= $expense['id'] ?>')"
                                                           <?= in_array($member['member_name'], $participantNames) ? 'checked' : '' ?>
                                                           style="margin-right: 0.5rem;">
                                                    <?= htmlspecialchars($member['member_name']) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
									
									
									
/***************************************************************/
3. JavaScript pour la gestion des checkboxes
Remplacez le JavaScript existant à la fin de group.php par :

/***************************************************************/

function toggleMemberFields() {
            const existingUser = document.querySelector('input[name="member_type"][value="existing_user"]');
            const newMember = document.querySelector('input[name="member_type"][value="new_member"]');
            const existingUserField = document.getElementById('existing_user_field');
            const newMemberField = document.getElementById('new_member_field');
            
            if (existingUser.checked) {
                existingUserField.classList.add('show');
                newMemberField.classList.remove('show');
                newMemberField.querySelector('input').value = '';
            } else if (newMember.checked) {
                newMemberField.classList.add('show');
                existingUserField.classList.remove('show');
                existingUserField.querySelector('select').value = '';
            }
        }
        
        function showEditForm(expenseId) {
            document.getElementById('edit-form-' + expenseId).style.display = 'block';
            // Mettre à jour l'état de "select all" quand on ouvre le formulaire
            updateSelectAllState('edit-' + expenseId);
        }
        
        function hideEditForm(expenseId) {
            document.getElementById('edit-form-' + expenseId).style.display = 'none';
        }
        
        function toggleAllParticipants(formType) {
            const selectAllCheckbox = document.getElementById('select-all-' + formType);
            const participantCheckboxes = document.querySelectorAll('.participant-checkbox-' + formType);
            
            participantCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }
        
        function updateSelectAllState(formType) {
            const selectAllCheckbox = document.getElementById('select-all-' + formType);
            const participantCheckboxes = document.querySelectorAll('.participant-checkbox-' + formType);
            
            const checkedCount = Array.from(participantCheckboxes).filter(cb => cb.checked).length;
            const totalCount = participantCheckboxes.length;
            
            if (checkedCount === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCount === totalCount) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        }
        
        // Initialiser l'état des checkboxes au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Pour le formulaire d'ajout
            if (document.getElementById('select-all-add')) {
                updateSelectAllState('add');
            }
        });
        
        // Fermer le modal en cliquant sur l'overlay
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.style.display = 'none';
            }
        });
		
		
		/***************************************************************/
4. CSS supplémentaire pour améliorer l'apparence
Ajoutez ces styles dans la section CSS :

/***************************************************************/

.checkbox-group label {
            transition: background-color 0.2s;
            padding: 0.25rem;
            border-radius: 4px;
        }
        
        .checkbox-group label:hover {
            background-color: #f3f4f6;
        }
        
        /* Style pour la checkbox "select all" */
        input[type="checkbox"]:indeterminate {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }
        
        input[type="checkbox"]:indeterminate::after {
            content: "";
            display: block;
            width: 6px;
            height: 2px;
            background: white;
            margin: 3px auto;
        }
		
		
		
/**********************************************************/
5. Validation côté client
Ajoutez cette validation dans le JavaScript pour s'assurer qu'au moins un participant est sélectionné :
/**********************************************************/


// Validation avant soumission
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const action = form.querySelector('input[name="action"]');
                    if (action && (action.value === 'add_expense' || action.value === 'edit_expense')) {
                        const participants = form.querySelectorAll('input[name="participants[]"]:checked');
                        if (participants.length === 0) {
                            e.preventDefault();
                            alert('Veuillez sélectionner au moins un participant pour cette dépense.');
                            return false;
                        }
                    }
                });
            });
        });