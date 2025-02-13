// Ensure that the Knockout ViewModel is initialized after the DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    function AdminLoginViewModel() {
        const self = this;
        self.email = ko.observable('');
        self.password = ko.observable('');
        self.errorMessage = ko.observable('');
        self.isSubmitting = ko.observable(false);

        self.submitForm = async function () {
            console.log('Submitting form with email:', self.email(), 'password:', self.password()); // Debugging
            self.isSubmitting(true);
            self.errorMessage(''); // Clear previous error

            const requestBody = {
                action: 'login',
                values: {
                    emailadd: self.email(),
                    password: self.password()
                }
            };

            try {
                const response = await fetch('classes/class.main.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                const data = await response.json();

                if (data.logged_in) {
                    if (data.record.role !== 3) {
                        self.errorMessage('You are not authorized to access this page.');
                    } else {
                        window.location.href = 'public/html/manage-shops.html'; // Redirect to shops page
                    }
                } else {
                    self.errorMessage(data.error || 'Login failed!');
                }
            } catch (error) {
                console.error('Error:', error);
                self.errorMessage('Something went wrong. Please try again.');
            } finally {
                self.isSubmitting(false);
            }
        };
    }

    // Apply Knockout bindings
    const loginViewModel = new AdminLoginViewModel();
    ko.applyBindings({ loginViewModel: loginViewModel });
});
