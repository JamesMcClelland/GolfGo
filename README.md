## Setup Instructions

### Prerequisites

* **PHP:** (Recommended version 7.4+)
* **Ngrok:** (Download from [ngrok.com](https://ngrok.com/)) Needed due to motion being SSL only

### Getting Started

1.  **Clone the Repository:**
    ```bash
    git clone https://github.com/JamesMcClelland/GolfGo.git
    cd GolfGo
    ```

2.  **Start PHP Web Server:**
    ```bash
    php -s 0.0.0.0:3344
    ```

3.  **Start Ngrok Tunnel (in a new terminal):**
    ```bash
    ngrok http http://localhost:3344
    ```

4.  **Access Pages (Replace `your_ngrok_url` with the URL Ngrok provides):**
    * **On Mobile (for recording):**
        Open `https://your_ngrok_url/` in your mobile browser.
        * Press "Record", allow sensor permissions, perform motion, then "Save".
    * **On Desktop (for playback):**
        Open `https://your_ngrok_url/read.php` in your desktop browser.
        * Select a recording from the list (defaults to latest), then "Play" or adjust sliders.

Screenshots:

![image](https://github.com/user-attachments/assets/c4e6749b-63ad-4dfd-8270-b1ee9d609516)

![image](https://github.com/user-attachments/assets/aeb440cd-1e02-4a65-a5b4-861efe138ad1)
