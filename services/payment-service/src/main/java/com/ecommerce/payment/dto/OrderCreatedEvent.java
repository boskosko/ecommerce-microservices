package com.ecommerce.payment.dto;

import lombok.Data;
import java.math.BigDecimal;

@Data
public class OrderCreatedEvent {
    private String event;
    private OrderData data;
    private String timestamp;

    @Data
    public static class OrderData {
        private String id;
        private String order_number;
        private Integer user_id;
        private String status;
        private BigDecimal total_amount;
    }
}