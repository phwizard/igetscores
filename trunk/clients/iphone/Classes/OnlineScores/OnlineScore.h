//
//  OnlineScore.h
//
//  Created by Andrew Kopanev on 3/23/09.
//  Copyright 2009 Injoit.com. All rights reserved.
//

#import <Foundation/Foundation.h>
#import "OAuthConsumer.h"
#import "OnlineScoreOperation.h"

@interface OnlineScore : NSObject {
	// temporary!!!
	id cdelegate;
	SEL cselector;
	//
	
	OAToken *requestToken, *accessToken;
	OAConsumer *consumer;
}

// public API
+ (OnlineScore*) getInstance;
- (void) setConsumerKey: (NSString*) key andSecret: (NSString*) secret;
- (void) getAccessTokenWithDelegate: (id) delegate callbackSelector: (SEL) selector;

- (OnlineScoreOperation*) getScores: (NSDictionary*) scoreDict 
						   delegate: (id) delegeate 
				   callbackSelector: (SEL) selector  
			   callbackFailSelector: (SEL) failSelector;

- (OnlineScoreOperation*) addScore: (NSDictionary*) scoreDict 
						  delegate: (id) delegeate 
				  callbackSelector: (SEL) selector  
			  callbackFailSelector: (SEL) failSelector;

// internal use

- (OnlineScoreOperation*) makeOAuthRequestWithParams: (NSDictionary*) dict
											  prefix: (NSString*) prefix 
											delegate: (id) delegeate 
									callbackSelector: (SEL) selector  
								callbackFailSelector: (SEL) failSelector;


@end
