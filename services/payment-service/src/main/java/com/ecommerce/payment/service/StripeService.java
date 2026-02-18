package com.ecommerce.payment.service;

import com.stripe.Stripe;
import com.stripe.exception.StripeException;
import com.stripe.model.PaymentIntent;
import com.stripe.param.PaymentIntentCreateParams;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

import jakarta.annotation.PostConstruct;
import java.math.BigDecimal;
import java.util.HashMap;
import java.util.Map;

@Service
@Slf4j
public class StripeService {

    @Value("${stripe.api.key}")
    private String stripeApiKey;

    @PostConstruct
    public void init() {
        Stripe.apiKey = stripeApiKey;
        log.info("Stripe API initialized");
    }

    /**
     * Create a payment intent with Stripe
     */
    public String createPaymentIntent(BigDecimal amount, String orderId) {
        log.info("Creating Stripe payment intent for order: {}, amount: {}", orderId, amount);

        try {
            // Convert amount to cents (Stripe expects smallest currency unit)
            long amountInCents = amount.multiply(BigDecimal.valueOf(100)).longValue();

            PaymentIntentCreateParams params = PaymentIntentCreateParams.builder()
                    .setAmount(amountInCents)
                    .setCurrency("usd")
                    .putMetadata("order_id", orderId)
                    .setAutomaticPaymentMethods(
                            PaymentIntentCreateParams.AutomaticPaymentMethods.builder()
                                    .setEnabled(true)
                                    .build()
                    )
                    .build();

            PaymentIntent paymentIntent = PaymentIntent.create(params);

            log.info("Stripe payment intent created: {}", paymentIntent.getId());

            return paymentIntent.getId();

        } catch (StripeException e) {
            log.error("Stripe payment failed: {}", e.getMessage());
            throw new RuntimeException("Payment processing failed: " + e.getMessage(), e);
        }
    }

    /**
     * Refund a payment
     */
    public void refundPayment(String paymentIntentId) {
        log.info("Refunding Stripe payment: {}", paymentIntentId);
        // TODO: Implement refund logic if needed
    }
}