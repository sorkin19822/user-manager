<main class="container py-4">
    <header>
        <h4 class="mb-4">User Manager</h4>
    </header>

    <div class="alert alert-danger d-none" id="pageError" role="alert"></div>

    <section class="d-flex gap-2 align-items-center mb-3" aria-label="Top user actions">
        <button type="button" class="btn btn-primary js-add-user">
            <i class="bi bi-plus-lg"></i> Add
        </button>

        <select class="form-select w-auto js-bulk-action" aria-label="Bulk action">
            <option value="">-Please Select-</option>
            <option value="set_active">Set active</option>
            <option value="set_inactive">Set not active</option>
            <option value="delete">Delete</option>
        </select>

        <button type="button" class="btn btn-secondary js-bulk-ok">OK</button>
    </section>

    <section class="table-responsive" aria-label="Users table">
        <table class="table table-striped table-hover align-middle">
            <caption class="visually-hidden">User list</caption>
            <thead>
                <tr>
                    <th scope="col">
                        <input class="form-check-input" type="checkbox" id="checkAllUsers" aria-label="Select all users">
                    </th>
                    <th scope="col">Name</th>
                    <th scope="col">Status</th>
                    <th scope="col">Role</th>
                    <th scope="col">Options</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <tr>
                    <td colspan="5" class="text-muted">Loading...</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="d-flex gap-2 align-items-center mt-3" aria-label="Bottom user actions">
        <button type="button" class="btn btn-primary js-add-user">
            <i class="bi bi-plus-lg"></i> Add
        </button>

        <select class="form-select w-auto js-bulk-action" aria-label="Bulk action">
            <option value="">-Please Select-</option>
            <option value="set_active">Set active</option>
            <option value="set_inactive">Set not active</option>
            <option value="delete">Delete</option>
        </select>

        <button type="button" class="btn btn-secondary js-bulk-ok">OK</button>
    </section>
</main>

<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content needs-validation" id="userForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Add user</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="userId">

                <div class="alert alert-danger d-none" id="userFormError" role="alert"></div>

                <div class="mb-3">
                    <label for="nameFirst" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="nameFirst" name="name_first" maxlength="100" required>
                    <div class="invalid-feedback">Use letters, spaces, hyphens, or apostrophes.</div>
                </div>

                <div class="mb-3">
                    <label for="nameLast" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="nameLast" name="name_last" maxlength="100" required>
                    <div class="invalid-feedback">Use letters, spaces, hyphens, or apostrophes.</div>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="user">user</option>
                        <option value="admin">admin</option>
                    </select>
                    <div class="invalid-feedback">Role is required.</div>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="status" name="status" checked>
                    <label class="form-check-label" for="status">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveUserButton">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Delete users</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Delete selected users?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteButton">Delete</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="warnNoUsersModal" tabindex="-1" aria-labelledby="warnNoUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="warnNoUsersModalLabel">No users selected</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Please select at least one user.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="warnNoActionModal" tabindex="-1" aria-labelledby="warnNoActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="warnNoActionModalLabel">No action selected</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Please select an action.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
