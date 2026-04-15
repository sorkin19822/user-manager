$(function () {
    const $tableBody = $('#usersTableBody');
    const $checkAll = $('#checkAllUsers');
    const $pageError = $('#pageError');
    const $pageErrorText = $('#pageErrorText');
    const $userForm = $('#userForm');
    const $userFormError = $('#userFormError');
    const $userModalTitle = $('#userModalLabel');
    const $saveUserButton = $('#saveUserButton');
    const $confirmDeleteTitle = $('#confirmDeleteModalLabel');
    const $confirmDeleteMessage = $('#confirmDeleteMessage');
    const userModal = new bootstrap.Modal('#userModal');
    const confirmDeleteModal = new bootstrap.Modal('#confirmDeleteModal');
    const warnNoUsersModal = new bootstrap.Modal('#warnNoUsersModal');
    const warnNoActionModal = new bootstrap.Modal('#warnNoActionModal');
    const namePattern = /^[\p{L}\p{M}]+(?:[ '\-][\p{L}\p{M}]+)*$/u;

    let deleteMode = null;
    let deleteIds = [];
    let originalUserFormData = null;

    /**
     * Shows a page-level error that can stay visible until the user closes it.
     */
    function showPageError(message) {
        $pageErrorText.text(message);
        $pageError.removeClass('d-none');
    }

    /**
     * Clears the page-level error without touching modal validation messages.
     */
    function clearPageError() {
        $pageErrorText.text('');
        $pageError.addClass('d-none');
    }

    /**
     * Extracts the backend error message and keeps one fallback path for all AJAX handlers.
     */
    function errorMessage(response, fallback) {
        return response && response.error && response.error.message
            ? response.error.message
            : fallback;
    }

    /**
     * Treats both HTTP 404 and the project-specific error code 100 as "record not found".
     */
    function isNotFoundResponse(xhr) {
        return xhr.status === 404
            || (xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.code === 100);
    }

    /**
     * Builds the status marker. Inactive is the default state; the `active` modifier is the only class toggle.
     */
    function statusBadge(status) {
        const isActive = status === true || status === 'active';
        return $('<span>')
            .addClass('status-dot rounded-circle')
            .toggleClass('active', isActive)
            .append($('<span>').addClass('visually-hidden').text(isActive ? 'active' : 'inactive'));
    }

    /**
     * Replaces the table body with one empty-state row.
     */
    function renderEmptyRow(message) {
        $tableBody.empty().append(
            $('<tr>').addClass('js-empty-row').append(
                $('<td>').attr('colspan', 5).addClass('text-muted').text(message)
            )
        );
    }

    /**
     * Keeps full-name formatting in one place for row text, checkbox data, and confirmations.
     */
    function userFullName(user) {
        return user.name_first + ' ' + user.name_last;
    }

    /**
     * Creates a complete table row and stores the backend user object on the edit button.
     * That cached object lets the edit modal open without an extra `/users/get/{id}` request.
     */
    function buildUserRow(user, checked) {
        const fullName = userFullName(user);
        const $checkbox = $('<input>')
            .addClass('form-check-input js-user-check')
            .attr({
                type: 'checkbox',
                'aria-label': 'Select user'
            })
            .val(user.id)
            .prop('checked', checked === true)
            .data('name', fullName);

        const $editButton = $('<button>')
            .addClass('btn btn-outline-primary btn-sm me-1 js-edit-user')
            .attr({
                type: 'button',
                title: 'Edit',
                'aria-label': 'Edit user'
            })
            .data('id', user.id)
            .data('name', fullName)
            .data('user', user)
            .append($('<i>').addClass('bi bi-pencil'));

        const $deleteButton = $('<button>')
            .addClass('btn btn-outline-danger btn-sm js-delete-user')
            .attr({
                type: 'button',
                title: 'Delete',
                'aria-label': 'Delete user'
            })
            .data('id', user.id)
            .data('name', fullName)
            .append($('<i>').addClass('bi bi-trash'));

        return $('<tr>')
            .attr('data-user-id', user.id)
            .append($('<td>').append($checkbox))
            .append($('<td>').addClass('js-user-name').text(fullName))
            .append($('<td>').addClass('js-user-status').append(statusBadge(user.status)))
            .append($('<td>').addClass('js-user-role').text(user.role))
            .append($('<td>').append($editButton, $deleteButton));
    }

    /**
     * Finds a user row by numeric id to avoid string/number comparison issues.
     */
    function userRow(id) {
        const numId = Number(id);
        return $tableBody.find('tr[data-user-id]').filter(function () {
            return Number($(this).attr('data-user-id')) === numId;
        });
    }

    /**
     * Adds a new user row without reloading or redrawing the full table.
     */
    function appendUserRow(user) {
        $tableBody.find('.js-empty-row').remove();
        $tableBody.append(buildUserRow(user, false));
        $checkAll.prop('checked', false);
    }

    /**
     * Replaces only the affected row after update and preserves its checkbox state.
     */
    function replaceUserRow(user) {
        const $row = userRow(user.id);
        const checked = $row.find('.js-user-check').is(':checked');

        if ($row.length) {
            $row.replaceWith(buildUserRow(user, checked));
        } else {
            appendUserRow(user);
        }

        syncMasterCheckbox();
    }

    /**
     * Removes only rows that are confirmed deleted or already missing on delete action.
     */
    function removeUserRows(ids) {
        ids.forEach(function (id) {
            userRow(id).remove();
        });

        if (!$tableBody.find('tr[data-user-id]').length) {
            renderEmptyRow('No users found.');
        }

        syncMasterCheckbox();
    }

    /**
     * Updates status cells for matched ids only and refreshes cached row data for future editing.
     */
    function setRowsStatus(ids, status) {
        const isActive = status === 'active';

        ids.forEach(function (id) {
            const $row = userRow(id);
            $row.find('.status-dot')
                .toggleClass('active', isActive)
                .find('.visually-hidden')
                .text(isActive ? 'active' : 'inactive');

            const $editBtn = $row.find('.js-edit-user');
            const cachedUser = $editBtn.data('user');
            if (cachedUser) {
                $editBtn.data('user', Object.assign({}, cachedUser, { status: isActive }));
            }
        });
    }

    /**
     * Clears selected checkboxes after a completed action and then syncs the master checkbox.
     */
    function uncheckUserRows(ids) {
        ids.forEach(function (id) {
            userRow(id).find('.js-user-check').prop('checked', false);
        });
        syncMasterCheckbox();
    }

    /**
     * Compares requested ids with ids actually matched by the backend.
     * Missing ids mean the page is stale, usually because another user changed the data.
     */
    function missingIds(requestedIds, matchedIds) {
        const matched = matchedIds.map(Number);

        return requestedIds.filter(function (id) {
            return !matched.includes(Number(id));
        });
    }

    /**
     * Reloads one row from the backend. This is a targeted fallback, not a full table refresh.
     */
    function refreshUserRow(id) {
        $.ajax({
            url: '/users/get/' + encodeURIComponent(id),
            method: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.status) {
                replaceUserRow(response.user);
                return;
            }

            removeUserRows([id]);
        }).fail(function (xhr) {
            if (isNotFoundResponse(xhr)) {
                removeUserRows([id]);
                return;
            }

            showPageError(errorMessage(xhr.responseJSON, 'Could not refresh user.'));
        });
    }

    /**
     * Keeps the header checkbox checked only when all visible user rows are selected.
     */
    function syncMasterCheckbox() {
        const total = $('.js-user-check').length;
        const checked = $('.js-user-check:checked').length;
        $checkAll.prop('checked', total > 0 && total === checked);
    }

    /**
     * Renders the initial or fallback user list. Normal actions should update rows instead.
     */
    function renderUsers(users) {
        $tableBody.empty();
        $checkAll.prop('checked', false);

        if (!users.length) {
            renderEmptyRow('No users found.');
            return;
        }

        users.forEach(function (user) {
            $tableBody.append(buildUserRow(user, false));
        });
    }

    /**
     * Fallback loader used only when the server-rendered JSON payload is missing or invalid.
     */
    function loadUsers() {
        clearPageError();
        renderEmptyRow('Loading...');

        $.ajax({
            url: '/users/list',
            method: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (!response.status) {
                showPageError(errorMessage(response, 'Could not load users.'));
                renderEmptyRow('No users found.');
                return;
            }

            renderUsers(response.users || []);
        }).fail(function () {
            showPageError('Could not load users.');
            renderEmptyRow('No users found.');
        });
    }

    /**
     * Reads users embedded into the page by the backend to avoid a duplicate `/users/list` request.
     */
    function initialUsers() {
        const raw = $('#initialUsersData').text();

        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    /**
     * Returns selected users with both id and display name for bulk actions and confirmations.
     */
    function selectedUsers() {
        return $('.js-user-check:checked').map(function () {
            const $checkbox = $(this);
            return {
                id: $checkbox.val(),
                name: $checkbox.data('name')
            };
        }).get();
    }

    /**
     * Restores the modal form to create-mode defaults and clears validation state.
     */
    function resetUserForm() {
        $userForm[0].reset();
        $userForm.removeClass('was-validated');
        $('#userId').val('');
        $('#status').prop('checked', true);
        $userFormError.text('').addClass('d-none');
        originalUserFormData = null;
        updateSaveButtonState();
    }

    /**
     * Opens the create/edit modal. Edit mode uses cached row data and stores the original form state.
     */
    function openUserModal(user) {
        resetUserForm();

        if (user) {
            $userModalTitle.text('Edit user');
            $('#userId').val(user.id);
            $('#nameFirst').val(user.name_first);
            $('#nameLast').val(user.name_last);
            $('#role').val(roleToFormValue(user.role));
            $('#status').prop('checked', user.status === true || user.status === 'active');
            originalUserFormData = formData();
        } else {
            $userModalTitle.text('Add user');
        }

        updateSaveButtonState();
        userModal.show();
    }

    /**
     * Converts backend role values into select option values.
     */
    function roleToFormValue(role) {
        return role === 'admin' ? '1' : '2';
    }

    /**
     * Converts select option values into backend role values.
     */
    function roleFromFormValue(role) {
        return role === '1' ? 'admin' : 'user';
    }

    /**
     * Collects and normalizes the current form values before validation or submit.
     */
    function formData() {
        return {
            name_first: $.trim($('#nameFirst').val()),
            name_last: $.trim($('#nameLast').val()),
            role: $('#role').val(),
            status: $('#status').is(':checked') ? 1 : 0
        };
    }

    /**
     * Applies custom validity for name fields so Bootstrap can display field-level feedback.
     */
    function validateNameField(selector) {
        const field = $(selector)[0];
        const value = $.trim(field.value);
        const isValid = value !== '' && namePattern.test(value);

        field.setCustomValidity(isValid ? '' : 'Invalid name');
    }

    /**
     * Runs all custom validations that are not covered by native `required` attributes.
     */
    function validateUserFormFields() {
        validateNameField('#nameFirst');
        validateNameField('#nameLast');
    }

    /**
     * Compares edit-mode form values with the snapshot taken when the modal was opened.
     */
    function hasUserFormChanged() {
        if (!originalUserFormData) {
            return true;
        }

        const current = formData();
        return Object.keys(originalUserFormData).some(function (key) {
            return String(originalUserFormData[key]) !== String(current[key]);
        });
    }

    /**
     * Disables Save in edit mode until the user changes something.
     */
    function updateSaveButtonState() {
        const isEdit = $('#userId').val() !== '';
        $saveUserButton.prop('disabled', isEdit && !hasUserFormChanged());
    }

    /**
     * Validates and submits create/update requests, then updates only the affected table row.
     */
    function submitUserForm() {
        validateUserFormFields();

        if (!$userForm[0].checkValidity()) {
            $userForm.addClass('was-validated');
            return;
        }

        if (!hasUserFormChanged()) {
            return;
        }

        const id = $('#userId').val();
        const url = id ? '/users/update/' + encodeURIComponent(id) : '/users/create';
        const submittedData = formData();

        $userFormError.text('').addClass('d-none');

        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: submittedData
        }).done(function (response) {
            if (!response.status) {
                $userFormError.text(errorMessage(response, 'Could not save user.')).removeClass('d-none');
                return;
            }

            if (id) {
                replaceUserRow(response.user);
            } else {
                appendUserRow({
                    id: response.id,
                    name_first: submittedData.name_first,
                    name_last: submittedData.name_last,
                    role: roleFromFormValue(submittedData.role),
                    status: submittedData.status === 1
                });
            }

            userModal.hide();
        }).fail(function (xhr) {
            if (id && isNotFoundResponse(xhr)) {
                userModal.hide();
                removeUserRows([id]);
                showPageError('This user was already removed. The table has been updated.');
                return;
            }

            $userFormError
                .text(errorMessage(xhr.responseJSON, 'Could not save user.'))
                .removeClass('d-none');
        });
    }

    /**
     * Prepares delete confirmation text. Bulk delete lists names so the user sees what will be removed.
     */
    function openDeleteModal(mode, users) {
        deleteMode = mode;
        deleteIds = users.map(function (user) {
            return user.id;
        });

        if (mode === 'single') {
            $confirmDeleteTitle.text('Delete user');
            $confirmDeleteMessage.empty().text('Delete ' + users[0].name + '?');
        } else {
            const $list = $('<ul>').addClass('mb-0');
            users.forEach(function (user) {
                $list.append($('<li>').text(user.name));
            });

            $confirmDeleteTitle.text('Delete users');
            $confirmDeleteMessage
                .empty()
                .append($('<p>').text('Delete selected users (' + users.length + ')?'))
                .append($list);
        }

        confirmDeleteModal.show();
    }

    /**
     * Executes single or bulk delete. Only delete actions are allowed to remove rows from the table.
     */
    function deleteSelected() {
        if (deleteMode === 'single') {
            $.ajax({
                url: '/users/delete/' + encodeURIComponent(deleteIds[0]),
                method: 'POST',
                dataType: 'json'
            }).done(function (response) {
                if (!response.status) {
                    showPageError(errorMessage(response, 'Could not delete user.'));
                    return;
                }

                confirmDeleteModal.hide();
                removeUserRows(deleteIds);
            }).fail(function (xhr) {
                if (isNotFoundResponse(xhr)) {
                    confirmDeleteModal.hide();
                    removeUserRows(deleteIds);
                    showPageError('This user was already removed. The table has been updated.');
                    return;
                }

                showPageError(errorMessage(xhr.responseJSON, 'Could not delete user.'));
            });
            return;
        }

        runBulkAction('delete', deleteIds, function () {
            confirmDeleteModal.hide();
            removeUserRows(deleteIds);
        });
    }

    /**
     * Sends one bulk request for all selected ids.
     * Status actions update matched rows; delete actions can remove stale rows because deletion was intended.
     */
    function runBulkAction(action, ids, afterSuccess) {
        clearPageError();

        $.ajax({
            url: '/users/bulk',
            method: 'POST',
            dataType: 'json',
            data: {
                action: action,
                ids: ids
            }
        }).done(function (response) {
            if (!response.status) {
                showPageError(errorMessage(response, 'Could not update users.'));
                return;
            }

            if (afterSuccess) {
                afterSuccess(response);
            }
        }).fail(function (xhr) {
            if (isNotFoundResponse(xhr)) {
                if (action === 'delete') {
                    removeUserRows(ids);
                    showPageError('Selected users were already removed. The table has been updated.');
                    return;
                }

                uncheckUserRows(ids);
                showPageError('Selected users could not be updated. The table may be outdated.');
                return;
            }

            showPageError(errorMessage(xhr.responseJSON, 'Could not update users.'));
        });
    }

    $('.js-add-user').on('click', function () {
        openUserModal(null);
    });

    $('#pageErrorClose').on('click', function () {
        clearPageError();
    });

    $('.js-bulk-ok').on('click', function () {
        const $actionSelect = $(this).siblings('.js-bulk-action');
        const action = $actionSelect.val();
        const users = selectedUsers();
        const ids = users.map(function (user) {
            return user.id;
        });

        if (!action) {
            warnNoActionModal.show();
            return;
        }

        if (!ids.length) {
            warnNoUsersModal.show();
            return;
        }

        if (action === 'delete') {
            openDeleteModal('bulk', users);
            return;
        }

        runBulkAction(action, ids, function (response) {
            const matchedIds = response.ids || ids;
            const staleIds = missingIds(ids, matchedIds);

            setRowsStatus(matchedIds, action === 'set_active' ? 'active' : 'inactive');
            uncheckUserRows(ids);

            if (staleIds.length) {
                showPageError('Some selected users could not be updated. The table may be outdated.');
            }

            $actionSelect.val('');
        });
    });

    $checkAll.on('change', function () {
        $('.js-user-check').prop('checked', $(this).is(':checked'));
    });

    $tableBody.on('change', '.js-user-check', function () {
        syncMasterCheckbox();
    });

    $tableBody.on('click', '.js-edit-user', function () {
        openUserModal($(this).data('user'));
    });

    $tableBody.on('click', '.js-delete-user', function () {
        const $button = $(this);
        openDeleteModal('single', [{
            id: $button.data('id'),
            name: $button.data('name')
        }]);
    });

    $userForm.on('submit', function (event) {
        event.preventDefault();
        submitUserForm();
    });

    $userForm.on('input change', 'input, select', function () {
        validateUserFormFields();
        updateSaveButtonState();
    });

    $('#confirmDeleteButton').on('click', function () {
        deleteSelected();
    });

    const users = initialUsers();
    if (users === null) {
        loadUsers();
    } else {
        renderUsers(users);
    }
});
