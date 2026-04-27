//จัดการสัญญา (manage_contract.html)
const loadContracts = async () => {
    const res = await callAdminAPI('get_contracts');
    // รอเอาข้อมูลไปใส่ Table ใน HTML
    console.log(res);
};

//จัดการพนักงาน (manage_employee.html)
const addEmployee = async (employeeData) => {
    const res = await callAdminAPI('add_employee', employeeData);
    if(res.success) alert('เพิ่มพนักงานสำเร็จ');
};

//จัดการใบแจ้งหนี้ (manage_invoice.html)
const createInvoice = async (tenantId, amount) => {
    return await callAdminAPI('create_invoice', { tenantId, amount });
};

// เตรียมจัดการการส่ง Form
const handleAdminForm = async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    
    // ส่งไปหา API ตามประเภทงาน
    // const result = await callAdminAPI('submit_form', data);
};