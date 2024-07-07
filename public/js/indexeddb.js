document.addEventListener('DOMContentLoaded', () => {
    let db;
    const request = indexedDB.open('formDataDB', 1);

    request.onupgradeneeded = (event) => {
        db = event.target.result;
        const objectStore = db.createObjectStore('formData', { keyPath: 'id', autoIncrement: true });
        objectStore.createIndex('name', 'name', { unique: false });
        objectStore.createIndex('age', 'age', { unique: false });
        objectStore.createIndex('email', 'email', { unique: true });
    };

    request.onsuccess = (event) => {
        db = event.target.result;
        loadTable();
    };

    request.onerror = (event) => {
        console.error('Database error:', event.target.errorCode);
    };

    document.getElementById('dataForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const name = document.getElementById('name').value;
        const age = document.getElementById('age').value;
        const email = document.getElementById('email').value;
        addData({ name, age, email });
    });

    function addData(data) {
        const transaction = db.transaction(['formData'], 'readwrite');
        const objectStore = transaction.objectStore('formData');
        const request = objectStore.add(data);

        request.onsuccess = () => {
            loadTable();
            document.getElementById('dataForm').reset(); // Reset the form fields
        };

        request.onerror = (event) => {
            console.error('Add data error:', event.target.errorCode);
        };
    }

    function loadTable() {
        const transaction = db.transaction(['formData'], 'readonly');
        const objectStore = transaction.objectStore('formData');
        const request = objectStore.getAll();

        request.onsuccess = (event) => {
            const data = event.target.result;
            const tableBody = document.querySelector('#dataTable tbody');
            tableBody.innerHTML = '';
            data.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.name}</td>
                    <td>${item.age}</td>
                    <td>${item.email}</td>
                `;
                tableBody.appendChild(row);
            });
        };

        request.onerror = (event) => {
            console.error('Load table error:', event.target.errorCode);
        };
    }
});
