
footer {
    width: 100%;
    /* Sử dụng nền #f4f7f6 để đồng bộ với nền 
       của .auth-wrapper (nền trang web của bạn) 
    */
    background-color: #f4f7f6;
    
    /* Một đường kẻ mỏng tinh tế ở trên */
    border-top: 1px solid #e0e4e8;
    
    padding: 25px 0; /* Tạo khoảng cách bên trong */
    text-align: center;
    
    /* Để footer luôn nằm ở dưới cùng nếu nội dung trang ngắn, 
       bạn có thể cần thêm các quy tắc CSS cho body/html, 
       nhưng hiện tại nó sẽ nằm ngay sau .auth-wrapper 
    */
}

footer p {
    margin: 0; /* Xóa khoảng cách mặc định của <p> */
    color: #888; /* Màu chữ xám, nhẹ nhàng */
    font-size: 14px;
    font-weight: 500;
}