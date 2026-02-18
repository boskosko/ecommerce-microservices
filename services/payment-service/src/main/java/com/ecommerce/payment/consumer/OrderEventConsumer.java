package com.ecommerce.payment.consumer;

import com.ecommerce.payment.config.RabbitMQConfig;
import com.ecommerce.payment.dto.OrderCreatedEvent;
import com.ecommerce.payment.dto.PaymentCompletedEvent;
import com.ecommerce.payment.model.Payment;
import com.ecommerce.payment.service.PaymentService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.amqp.rabbit.annotation.RabbitListener;
import org.springframework.amqp.rabbit.core.RabbitTemplate;
import org.springframework.stereotype.Component;

@Component
@RequiredArgsConstructor
@Slf4j
public class OrderEventConsumer {

    private final PaymentService paymentService;
    private final RabbitTemplate rabbitTemplate;

    @RabbitListener(queues = RabbitMQConfig.PAYMENT_QUEUE)
    public void handleOrderCreated(OrderCreatedEvent event) {
        log.info("Received order.created event for order: {}", event.getData().getId());

        try {
            String orderId = event.getData().getId();

            // Create payment
            Payment payment = paymentService.createPayment(
                orderId,
                event.getData().getTotal_amount()
            );

            log.info("Payment created with ID: {} for order: {}", payment.getId(), orderId);

            // Process payment automatically
            payment = paymentService.processPayment(payment.getId());

            log.info("Payment processed: {}, status: {}", payment.getId(), payment.getStatus());

            // Publish payment.completed event
            publishPaymentCompletedEvent(payment);

        } catch (Exception e) {
            log.error("Failed to process payment for order: {}", event.getData().getId(), e);
        }
    }

    private void publishPaymentCompletedEvent(Payment payment) {
        PaymentCompletedEvent.PaymentData paymentData = new PaymentCompletedEvent.PaymentData(
            payment.getId(),
            payment.getOrderId(),
            payment.getAmount(),
            payment.getStatus().toString(),
            payment.getStripePaymentIntentId()
        );

        PaymentCompletedEvent event = new PaymentCompletedEvent(
            "payment.completed",
            paymentData,
            java.time.LocalDateTime.now().toString()
        );

        rabbitTemplate.convertAndSend(
            RabbitMQConfig.PAYMENT_EXCHANGE,
            "payment.completed",
            event
        );

        log.info("Published payment.completed event for order: {}", payment.getOrderId());
    }
}