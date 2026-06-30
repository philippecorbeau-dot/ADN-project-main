CREATE TABLE IF NOT EXISTS investment_opportunity_clicks (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT DEFAULT NULL,
    product_type VARCHAR(50) NOT NULL,
    action VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent LONGTEXT DEFAULT NULL,
    clicked_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    referrer VARCHAR(255) DEFAULT NULL,
    INDEX IDX_5A7FD108A76ED395 (user_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE investment_opportunity_clicks 
ADD CONSTRAINT FK_5A7FD108A76ED395 
FOREIGN KEY (user_id) REFERENCES users_adn (id);

