// Define the User model with all properties from the response
function User(data) {
    this.id = data.id;  // User ID
    this.username = ko.observable(data.username);
    this.email = ko.observable(data.emailadd);
    this.role = ko.observable(data.role);  // e.g., Admin, User
    this.status = data.status;  // 1: Active, 0: Inactive
    this.created_at = ko.observable(data.created_at);
    this.updated_at = ko.observable(data.updated_at);

    this.formattedStatus = ko.computed(() => {
        switch (this.status) {
            case 0:
                return "Pending";
            case 1:
                return "Active";
            case 2:
                return "Deactivated";
            default:
                return "Unknown";
        }
    });
}

function ManageUsersViewModel() {
    const self = this;

    // Observables
    self.users = ko.observableArray([]);
    self.isLoading = ko.observable(false);
    self.errorMessage = ko.observable("");
    self.isModalVisible = ko.observable(false);
    self.modalTitle = ko.observable("");
    self.modalContent = ko.observable("");
    self.statusLabel = ko.observable("");  // Used to display status in the modal

    const apiUrl = 'http://174.138.43.212:8000/classes/class.main.php';  // Update API URL if necessary

    // Load users from the backend
    self.loadUsers = function() {
        self.isLoading(true);
        fetch(apiUrl, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: "fetch-all-users",
                values: {}
            })
        })
        .then(response => response.json())
        .then(data => {
            if (Array.isArray(data)) {
                self.users(data.map(user => new User(user)));  
            } else {
                self.showError(data.message || "Error fetching users");
            }
        })
        .catch(error => {
            console.error("Fetch Error:", error);
            self.showError("An error occurred while loading users");
        })
        .finally(() => {
            self.isLoading(false);
        });
    };

    // Unified function to update user status
    self.updateUserStatus = function(user, status) {
        const statusMessages = {
            1: "activated",
            2: "deactivated"
        };

        fetch(apiUrl, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: "update-user-status",
                values: {
                    user_id: user.id,
                    status: status
                }
            })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message || `User ${statusMessages[status]} successfully!`);
            self.loadUsers();  // Reload the user list after the status update
        })
        .catch(error => {
            console.error("Error updating status:", error);
            alert("An error occurred while updating user status");
        });
    };

    self.logout = function() {
        fetch(apiUrl, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: "logout",
                values: {}
            })
        })
        .then(response => response.json())
        .then(data => {
            window.location.href="../../index.html"
        })
        .catch(error => {
            console.error("Error logging out:", error);
            alert("An error occurred while logging out");
        });
    };

    // Button action handlers
    self.activateUser = function(user) {
        self.updateUserStatus(user, 1);  // 1 for ACTIVE
    };

    self.deactivateUser = function(user) {
        self.updateUserStatus(user, 2);  // 2 for DEACTIVATED
    };

    // View Profile Picture in a Modal
    self.viewProfile = function(user) {
        if (!user.profile_pic()) {
            alert("No profile picture available.");
            return;
        }

        self.modalTitle(`Profile Picture of ${user.username()}`);
        self.modalContent(user.profile_pic());  // Assuming base64 image data or URL
        self.isModalVisible(true);
    };

    // Close Modal
    self.closeModal = function() {
        self.isModalVisible(false);
        self.modalTitle("");
        self.modalContent("");
    };

    // Display error messages
    self.showError = function(message) {
        self.errorMessage(message);
        setTimeout(() => self.errorMessage(""), 3000);
    };

    // Initial load of users
    self.loadUsers();
}

ko.applyBindings(new ManageUsersViewModel() );

