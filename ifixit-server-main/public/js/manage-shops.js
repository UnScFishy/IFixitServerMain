// Define the Shop model with all properties from the response
function Shop(data) {
    this.id = data.id;  // Shop ID
    this.shop_name = ko.observable(data.shop_name);
    this.owner_name = ko.observable(data.owner_name);
    this.business_permit = ko.observable(data.business_permit);  // Base64 permit for modal view
    this.profile_pic = ko.observable(data.profile_pic);  // Optional
    this.latitude = ko.observable(data.latitude);
    this.longitude = ko.observable(data.longitude);
    this.qrcode = ko.observable(data.qrcode);
    this.created_at = ko.observable(data.created_at);
    this.updated_at = ko.observable(data.updated_at);
    this.status = data.status;  // Add status field to check for filtering

    this.formattedStatus = ko.computed(() => {
        switch (this.status) {
            case 0:
                return "Pending";
            case 1:
                return "Verified";
            case 2:
                return "Denied";
            case 3:
                return "Deactivated";
            default:
                return "Unknown";
        }
    });
}

function ManageShopsViewModel() {
    const self = this;

    // Observables
    self.shops = ko.observableArray([]);
    self.isLoading = ko.observable(false);
    self.errorMessage = ko.observable("");
    self.isModalVisible = ko.observable(false);
    self.modalTitle = ko.observable("");
    self.modalContent = ko.observable("");
    self.modalIsPDF = ko.observable(false);  // To toggle between displaying PDFs and images

    const apiUrl = 'http://174.138.43.212:8000/classes/class.main.php';  // Update API URL if necessary

    // Load shops from the backend
    self.loadShops = function() {
        self.isLoading(true);
        fetch(apiUrl, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: "fetch-all-shops",
                values: {}
            })
        })
        .then(response => response.json())
        .then(data => {
            if (Array.isArray(data)) {
                self.shops(data.map(shop => new Shop(shop)));
            } else {
                self.showError(data.message || "Error fetching shops");
            }
        })
        .catch(error => {
            console.error("Fetch Error:", error);
            self.showError("An error occurred while loading shops");
        })
        .finally(() => {
            self.isLoading(false);
        });
    };

    // Unified function to update shop status
    self.updateShopStatus = function(shop, status) {
        const statusMessages = {
            1: "verified",
            2: "denied",
            3: "deactivated"
        };

        fetch(apiUrl, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: "update-shop-status",
                values: {
                    shop_id: shop.id,
                    status: status
                }
            })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message || `Shop ${statusMessages[status]} successfully!`);
            self.loadShops();  // Reload the shop list after the status update
        })
        .catch(error => {
            console.error("Error updating status:", error);
            alert("An error occurred while updating shop status");
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
    self.verifyShop = function(shop) {
        self.updateShopStatus(shop, 1);  // 1 for VERIFIED
    };

    self.denyShop = function(shop) {
        self.updateShopStatus(shop, 2);  // 2 for DENIED
    };

    self.deactivateShop = function(shop) {
        self.updateShopStatus(shop, 3);  // 3 for DEACTIVATED
    };

    // View Permit in a New Tab
    self.viewPermit = function(shop) {
        let base64PDF = shop.business_permit();

        // Check if the business permit is available
        if (!base64PDF) {
            console.error("No business permit available for this shop.");
            alert("No business permit available.");
            return;
        }

        // Add the "data:application/pdf;base64," prefix if it's missing
        if (!base64PDF.startsWith("data:application/pdf;base64,")) {
            base64PDF = "data:application/pdf;base64," + base64PDF;
        }

        try {
            const byteCharacters = atob(base64PDF.split(",")[1]);  // Decode base64 to binary
            const byteNumbers = Array.from(byteCharacters).map(char => char.charCodeAt(0));
            const byteArray = new Uint8Array(byteNumbers);
            const blob = new Blob([byteArray], { type: 'application/pdf' });

            const pdfUrl = URL.createObjectURL(blob);  // Create a blob URL for the PDF

            console.log("Opening PDF in new tab:", pdfUrl);
            window.open(pdfUrl, '_blank');  // Open PDF in a new browser tab
        } catch (error) {
            console.error("Error displaying PDF:", error);
            alert("Failed to display business permit.");
        }
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

    // Initial load of shops
    self.loadShops();
}

ko.applyBindings(new ManageShopsViewModel() );
