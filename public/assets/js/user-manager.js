$(function () {
    const $tableBody = $('#usersTableBody');
    const $checkAll = $('#checkAllUsers');
    const $pageError = $('#pageError');
    const $userForm = $('#userForm');
    const $userFormError = $('#userFormError');
    const $userModalTitle = $('#userModalLabel');
    const userModal = new bootstrap.Modal('#userModal');
    const confirmDeleteModal = new bootstrap.Modal('#confirmDeleteModal');
    const warnNoUsersModal = new bootstrap.Modal('#warnNoUsersModal');
    const warnNoActionModal = new bootstrap.Modal('#warnNoActionModal');

    let deleteMode = null;
    let deleteIds = [];

    function showPageError(message) {
        $pageError.text(message).removeClass('d-none');
    }

    function clearPageError() {
        $pageError.text('').addClass('d-none');
    }

    function errorMessage(response, fallback) {
        return response && response.error && response.error.message
            ? response.error.message
            : fallback;
    }

    function statusBadge(status) {
        const isActive = status === true || status === 'active';
        return $('<span>')
            .addClass('badge rounded-circle p-2')
            .addClass(isActive ? 'text-bg-success' : 'text-bg-secondary')
            .append($('<span>').addClass('visually-hidden').text(isActive ? 'active' : 'inactive'));
    }

    function renderEmptyRow(message) {
        $tableBody.empty().append(
            $('<tr>').append(
                $('<td>').attr('colspan', 5).addClass('text-muted').text(message)
            )
        );
    }

    function renderUsers(users) {
        $tableBody.empty();
        $checkAll.prop('checked', false);

        if (!users.length) {
            renderEmptyRow('No users found.');
            return;
        }

        users.forEach(function (user) {
            const $checkbox = $('<input>')
                .addClass('form-check-input js-user-check')
                .attr({
                    type: 'checkbox',
                    'aria-label': 'Select user'
                })
                .val(user.id);

            const $editButton = $('<button>')
                .addClass('btn btn-outline-primary btn-sm me-1 js-edit-user')
                .attr({
                    type: 'button',
                    title: 'Edit',
                    'aria-label': 'Edit user'
                })
                .data('id', user.id)
                .append($('<i>').addClass('bi bi-pencil'));

            const $deleteButton = $('<button>')
                .addClass('btn btn-outline-danger btn-sm js-delete-user')
                .attr({
                    type: 'button',
                    title: 'Delete',
                    'aria-label': 'Delete user'
                })
                .data('id', user.id)
                .append($('<i>').addClass('bi bi-trash'));

            $tableBody.append(
                $('<tr>')
                    .append($('<td>').append($checkbox))
                    .append($('<td>').text(user.name_first + ' ' + user.name_last))
                    .append($('<td>').append(statusBadge(user.status)))
                    .append($('<td>').text(user.role))
                    .append($('<td>').append($editButton, $deleteButton))
            );
        });
    }

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

    function selectedIds() {
        return $('.js-user-check:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function syncBulkActions(value) {
        $('.js-bulk-action').val(value);
    }

    function resetUserForm() {
        $userForm[0].reset();
        $('#userId').val('');
        $('#status').prop('checked', true);
        $userFormError.text('').addClass('d-none');
    }

    function openUserModal(user) {
        resetUserForm();

        if (user) {
            $userModalTitle.text('Edit user');
            $('#userId').val(user.id);
            $('#nameFirst').val(user.name_first);
            $('#nameLast').val(user.name_last);
            $('#role').val(user.role);
            $('#status').prop('checked', user.status === true || user.status === 'active');
        } else {
            $userModalTitle.text('Add user');
        }

        userModal.show();
    }

    function formData() {
        return {
            name_first: $.trim($('#nameFirst').val()),
            name_last: $.trim($('#nameLast').val()),
            role: $('#role').val(),
            status: $('#status').is(':checked') ? 1 : 0
        };
    }

    function submitUserForm() {
        const id = $('#userId').val();
        const url = id ? '/users/update/' + encodeURIComponent(id) : '/users/create';

        $userFormError.text('').addClass('d-none');

        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: formData()
        }).done(function (response) {
            if (!response.status) {
                $userFormError.text(errorMessage(response, 'Could not save user.')).removeClass('d-none');
                return;
            }

            userModal.hide();
            loadUsers();
        }).fail(function (xhr) {
            $userFormError
                .text(errorMessage(xhr.responseJSON, 'Could not save user.'))
                .removeClass('d-none');
        });
    }

    function openDeleteModal(mode, ids) {
        deleteMode = mode;
        deleteIds = ids;
        confirmDeleteModal.show();
    }

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
                loadUsers();
            }).fail(function (xhr) {
                showPageError(errorMessage(xhr.responseJSON, 'Could not delete user.'));
            });
            return;
        }

        runBulkAction('delete', deleteIds, function () {
            confirmDeleteModal.hide();
        });
    }

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
                afterSuccess();
            }
            syncBulkActions('');
            loadUsers();
        }).fail(function (xhr) {
            showPageError(errorMessage(xhr.responseJSON, 'Could not update users.'));
        });
    }

    $('.js-add-user').on('click', function () {
        openUserModal(null);
    });

    $('.js-bulk-action').on('change', function () {
        syncBulkActions($(this).val());
    });

    $('.js-bulk-ok').on('click', function () {
        const action = $('.js-bulk-action').first().val();
        const ids = selectedIds();

        if (!ids.length && action) {
            warnNoUsersModal.show();
            return;
        }

        if (ids.length && !action) {
            warnNoActionModal.show();
            return;
        }

        if (!ids.length && !action) {
            return;
        }

        if (action === 'delete') {
            openDeleteModal('bulk', ids);
            return;
        }

        runBulkAction(action, ids);
    });

    $checkAll.on('change', function () {
        $('.js-user-check').prop('checked', $(this).is(':checked'));
    });

    $tableBody.on('change', '.js-user-check', function () {
        const total = $('.js-user-check').length;
        const checked = $('.js-user-check:checked').length;
        $checkAll.prop('checked', total > 0 && total === checked);
    });

    $tableBody.on('click', '.js-edit-user', function () {
        const id = $(this).data('id');

        $.ajax({
            url: '/users/get/' + encodeURIComponent(id),
            method: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (!response.status) {
                showPageError(errorMessage(response, 'Could not load user.'));
                return;
            }

            openUserModal(response.user);
        }).fail(function (xhr) {
            showPageError(errorMessage(xhr.responseJSON, 'Could not load user.'));
        });
    });

    $tableBody.on('click', '.js-delete-user', function () {
        openDeleteModal('single', [$(this).data('id')]);
    });

    $userForm.on('submit', function (event) {
        event.preventDefault();
        submitUserForm();
    });

    $('#confirmDeleteButton').on('click', function () {
        deleteSelected();
    });

    loadUsers();
});
