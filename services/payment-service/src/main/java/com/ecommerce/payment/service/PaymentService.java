package com.ecommerce.payment.service;

import com.ecommerce.payment.model.Payment;
import com.ecommerce.payment.model.PaymentStatus;
import com.ecommerce.payment.repository.PaymentRepository;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.math.BigDecimal;

@Service
@RequiredArgsConstructor
@Slf4j
public class PaymentService {

    private final PaymentRepository paymentRepository;
    private final StripeService stripeService;

    @Transactional
    public Payment createPayment(String orderId, BigDecimal amount) {
        log.info("Creating payment for order: {}, amount: {}", orderId, amount);

        // Check if payment already exists
        if (paymentRepository.findByOrderId(orderId).isPresent()) {
            log.warn("Payment already exists for order: {}", orderId);
            throw new RuntimeException("Payment already exists for this order");
        }

        // Create payment record
        Payment payment = new Payment();
        payment.setOrderId(orderId);
        payment.setAmount(amount);
        payment.setStatus(PaymentStatus.PENDING);

        payment = paymentRepository.save(payment);
        log.info("Payment created with ID: {}", payment.getId());

        return payment;
    }

    @Transactional
    public Payment processPayment(Long paymentId) {
        log.info("Processing payment ID: {}", paymentId);

        Payment payment = paymentRepository.findById(paymentId)
                .orElseThrow(() -> new RuntimeException("Payment not found"));

        if (payment.getStatus() != PaymentStatus.PENDING) {
            log.warn("Payment {} is not in PENDING status: {}", paymentId, payment.getStatus());
            throw new RuntimeException("Payment is not in pending status");
        }

        payment.setStatus(PaymentStatus.PROCESSING);
        payment = paymentRepository.save(payment);

        try {
            // Process payment with Stripe
            String paymentIntentId = stripeService.createPaymentIntent(
                payment.getAmount(),
                payment.getOrderId()
            );

            payment.setStripePaymentIntentId(paymentIntentId);
            payment.setStatus(PaymentStatus.COMPLETED);

            log.info("Payment {} completed successfully", paymentId);

        } catch (Exception e) {
            log.error("Payment {} failed: {}", paymentId, e.getMessage());
            payment.setStatus(PaymentStatus.FAILED);
            payment.setErrorMessage(e.getMessage());
        }

        return paymentRepository.save(payment);
    }

    public Payment getPaymentByOrderId(String orderId) {
        return paymentRepository.findByOrderId(orderId)
                .orElseThrow(() -> new RuntimeException("Payment not found for order: " + orderId));
    }
}