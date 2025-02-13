function Booking(data) {
    this.booking_id = data.booking_id;
    this.shop_name = ko.observable(data.shop_name);
    this.customer_name = ko.observable(data.customer_name);
    this.service_type_id = data.service_type;  // Service type as ID
    this.status = data.status;  // Status as number
    this.date = ko.observable(data.date || 'N/A');
    this.notes = ko.observable(data.notes || 'No additional notes.');

    // Map for service type categories
    const serviceTypeMap = {
        1: 'Bicycle',
        2: 'Motorbike',
        3: 'Tricycle',
        4: 'Car'
    };

    // Map for status
    const statusMap = {
        1: 'Pending',
        2: 'Accepted',
        3: 'Declined',
        4: 'Payment Processing',
        5: 'Completed',
    };

    // Computed observable for formatted service type
    this.formattedServiceType = ko.computed(() => {
        return serviceTypeMap[this.service_type_id] || 'Unknown';
    });

    // Computed observable for formatted status
    this.formattedStatus = ko.computed(() => {
        return statusMap[this.status] || 'Unknown';
    });

    this.formattedDate = ko.computed(() => {
        const date = new Date(this.date());
        return isNaN(date) ? 'Invalid Date' : date.toLocaleDateString();  // Returns date only
    });
}


function BookingHistoryViewModel() {
    const self = this;

    self.bookings = ko.observableArray([]);
    self.isLoading = ko.observable(false);
    self.errorMessage = ko.observable("");
    self.isModalVisible = ko.observable(false);
    self.selectedBooking = ko.observable(null);
    self.modalTitle = ko.observable("Booking Details");

    const apiUrl = 'http://174.138.43.212:8000/classes/class.main.php';

    self.loadBookings = function() {
        self.isLoading(true);
        fetch(apiUrl, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: "get-all-bookings-history"
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (Array.isArray(data)) {
                self.bookings(data.map(booking => new Booking(booking)));
            } else {
                self.showError("Error: response was not an array of bookings");
            }
        })
        
        .catch(error => {
            console.error("Fetch Error:", error);
            self.showError("An error occurred while loading bookings.");
        })
        .finally(() => {
            self.isLoading(false);
        });
    };

    self.viewDetails = function(booking) {
        self.selectedBooking(booking);
        self.isModalVisible(true);
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

    self.closeModal = function() {
        self.isModalVisible(false);
        self.selectedBooking(null);
    };

    self.showError = function(message) {
        self.errorMessage(message);
        setTimeout(() => self.errorMessage(""), 3000);
    };

    // Initial load of bookings
    self.loadBookings();
}

ko.applyBindings(new BookingHistoryViewModel());
