package com.ecommerce.payment.dto;

import lombok.AllArgsConstructor;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.math.BigDecimal;
import java.time.LocalDateTime;

@Data
@AllArgsConstructor
@NoArgsConstructor
public class PaymentCompletedEvent {
    private String event = "payment.completed";
    private PaymentData data;
    private String timestamp = LocalDateTime.now().toString();

    @Data
    @AllArgsConstructor
    @NoArgsConstructor
    public static class PaymentData {
        private Long paymentId;
        private String orderId;
        private BigDecimal amount;
        private String status;
        private String stripePaymentIntentId;
    }
}