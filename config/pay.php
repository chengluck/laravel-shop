<?php

return [
    'alipay' => [
        'app_id'         => '2016092700605302',
        'ali_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAkUSyL+QOlxQHvbcI0UxgQFbWJtmFBUlW/6f7XjfYUXEHwc1pqxNQtGkf1iyzcw2SRLhGzF1VUIUi+w23HGjTUxV46kGWdlqtysoAKwXnhuTKo6OGnMRU71oJCX/jWi/YKCHxBnUuQ6oKSaSdLMB2nxCt6UsnJhlkCllFrJcV7uDoxO10pM2x/GcTauT8sy6yCiZpggyIR1MERsV+QxzSnLkkXU5RL6wjKkO+rf6N0tybTXxV+EuREjRZIjvztsjNPHaZPCXc0SJwD4D5c0YMUKLMqRLmuOFduqaMA3cMGxMrGY+EilJLmy+RaR5VONdFJun5KvGFM/2cTto6rh0YHwIDAQAB',
        'private_key'    => 'MIIEowIBAAKCAQEAkUSyL+QOlxQHvbcI0UxgQFbWJtmFBUlW/6f7XjfYUXEHwc1pqxNQtGkf1iyzcw2SRLhGzF1VUIUi+w23HGjTUxV46kGWdlqtysoAKwXnhuTKo6OGnMRU71oJCX/jWi/YKCHxBnUuQ6oKSaSdLMB2nxCt6UsnJhlkCllFrJcV7uDoxO10pM2x/GcTauT8sy6yCiZpggyIR1MERsV+QxzSnLkkXU5RL6wjKkO+rf6N0tybTXxV+EuREjRZIjvztsjNPHaZPCXc0SJwD4D5c0YMUKLMqRLmuOFduqaMA3cMGxMrGY+EilJLmy+RaR5VONdFJun5KvGFM/2cTto6rh0YHwIDAQABAoIBACGIUflsE1IcdYz9azOlBbLUWKqvG12VCFgLVqLxESX7iWbbG8E5vC9o9MhjmSi9wT3Lq8wQ31iu4txA1jvglqdfFYI9kZXQaL2e01sbCc7BkWUkojYdu91kUyG2O6zdzm+1JgXvlrZX0fgd34otAzTEjOCFUIwi4EzjPooQdielufn3uE5A/l5EHohdNN+DONVyppD9ZvOJApBlU/qmCKlnOcXyjHqaidTYHidH9WqlvbYfHKjuCXIWuiPE/W2/K0Fhl1rpjTfcJbdgmeJN9Mkacyg4/HuVNjUcoGYoPbtntc/1fLQzp6u8j1MqV4Nf9M4dowqQ086+8qnqijlCU4ECgYEAyQ0C6ejpVd9XhIwwh5nf3tgehDKnLM7ZQ6nlpJy+UlE1/p+efsb0s4qfXJUsyhubV+4k4z7AmDC/52H0p5x0RRx+bur/KDICvEFAa0n1BxS6dKfC2QbaeT8nebZQLh91PRimINWCjbxnFWgkjWNQCsHXCqGmRFzl4IbjRvYJAhcCgYEAuPi7zEqO/k8by9l/TPJz6agoLuTr7JR/4GchkeZecfRUqLMN3l7ZvzyDKjIqw4lO7id79AKZ9X59lBhJ/AxO9trYNB+WY+mPBjD4pfBTM69DPuo6cuGEFkMhkT0ql4ZJkYvA98gHc7F+yXHaU9qL0/I7Y+fbmAfq5kZ0g0p7BzkCgYACjJ6v1ps20okqjhiDb6kOC1F/vaCvCcRpfjsCNlaXdp4np2B8HQu3Rxe0NdQGkAkNOWDQXNhWVa/pQC24/lvfEHht8Z7gpJmyR2WItrxbpaCjoAjxdYvJo8pdWbl0jEORTcG1gt+P6oaoF9T20f6O1Fxkrx4Lmd30VeGF4dLFawKBgAmFG71G9RcXoTmbpxahv999vRu0woO5nN9Cz5J/xcqdpaHNHWCdhx11ktagIF1R+tL9Cz8ixyPAb9woZ95mD8ZauxfrrETWJ3tNF+8KcG3Pjml1iq6Q9shiih68hC2qRq0MAVF/ZQrKTtk1V+RK8jllVTMuIrovZiKV67c6JRzZAoGBAIyQl2ApLlMa0q9otfov0oO8QJTWzcQ95lBbWdyZ0bjIoTSb2lWt1hfPrxhK36IjzNCyg0/M1edhzSiD9LaMEOybT34Q9gT2Ktnp6ZUOYRUbxGi/rjy23/RqVLZMnQVEvSAm9+rq+FKnekFHhPgh23iA6db2YCiHL8oSYEQ/EfiH',
        'log'            => [
            'file' => storage_path('logs/alipay.log'),
        ],
    ],

    'wechat' => [
        'app_id'      => '',
        'mch_id'      => '',
        'key'         => '',
        'cert_client' => '',
        'cert_key'    => '',
        'log'         => [
            'file' => storage_path('logs/wechat_pay.log'),
        ],
    ],
];
