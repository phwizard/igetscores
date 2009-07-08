//
//  OnlineScore.m
//
//  Created by Andrew Kopanev on 3/23/09.
//  Copyright 2009 Injoit.com. All rights reserved.
//

#import "OnlineScore.h"

static NSOperationQueue *operationQueue;
static NSInteger  operationCounter;

static NSString *baseURL = @"http://www.igetscores.com/hs2/";

static NSString *getReqTokenURL = @"http://www.igetscores.com/hs2/get_req_token.php";
static NSString *getAccTokenURL = @"http://www.igetscores.com/hs2/get_acc_token.php";

@implementation OnlineScore

+ (OnlineScore*) getInstance {
	static OnlineScore *sharedSingleton = nil;
	
	@synchronized(self) {
		if (!sharedSingleton) {
			sharedSingleton = [[OnlineScore alloc] init];
			
			// operations init
			operationCounter = 0;
			operationQueue = [[NSOperationQueue alloc] init];
			[operationQueue setMaxConcurrentOperationCount: 5];			
		}
	}
	return sharedSingleton;
}

- (void) setConsumerKey: (NSString*) key andSecret: (NSString*) secret {
	if (consumer) {
		[consumer release];
	}	
	consumer = [[OAConsumer alloc] initWithKey: key
										secret: secret];
}

- (OnlineScoreOperation*) addScore: (NSDictionary*) dict 
						  delegate: (id) delegeate 
				  callbackSelector: (SEL) selector  
			  callbackFailSelector: (SEL) failSelector {
	if (dict && accessToken) {
		OnlineScoreOperation* op = [self makeOAuthRequestWithParams: dict
														  prefix: @"add_score.php?"
														  delegate: delegeate
												   callbackSelector: selector
											   callbackFailSelector: failSelector];
		return op;
	}
	return nil;	
}

- (OnlineScoreOperation*) getScores: (NSDictionary*) dict 
						  delegate: (id) delegeate 
				  callbackSelector: (SEL) selector  
			  callbackFailSelector: (SEL) failSelector {
	if (dict && accessToken) {
		OnlineScoreOperation* op = [self makeOAuthRequestWithParams: dict
															 prefix: @"get_scores.php?"
														  delegate: delegeate
												   callbackSelector: selector
											   callbackFailSelector: failSelector];
		return op;
	}
	return nil;	
}

- (OnlineScoreOperation*) makeOAuthRequestWithParams: (NSDictionary*) dict
											  prefix: (NSString*) prefix 
											delegate: (id) delegeate 
									callbackSelector: (SEL) selector  
								callbackFailSelector: (SEL) failSelector {
	NSMutableArray *params;
	params = [[NSMutableArray alloc] init];
	
	OARequestParameter *param;
	for (NSString* key in dict) {
		param = [[OARequestParameter alloc] initWithName: key value: [dict objectForKey: key]];
		[params addObject: param];
		[param release];
	}
	
	param = [[OARequestParameter alloc] initWithName: @"device_id" value: [[UIDevice currentDevice] uniqueIdentifier]];
	[params addObject: param];
	[param release];
	
	OAMutableURLRequest *request = [[OAMutableURLRequest alloc] initWithURL: [NSURL URLWithString: [NSString stringWithFormat: @"%@%@", baseURL, prefix]]
																   consumer: consumer
																	  token: accessToken
																	  realm: nil   // our service provider doesn't specify a realm
														  signatureProvider: nil]; // use the default method, HMAC-SHA1		
	
	[request setHTTPMethod: @"POST"];
	[request setParameters: params];	
	
	OnlineScoreOperation *op = [OnlineScoreOperation queueDataRequest: request
													   withIdentifier: ++operationCounter
												   withCallbackTarget: delegeate 
										   withCallbackFinishSelector: selector
											 withCallbackFailSelector: failSelector];
	[params dealloc]; 
//	[request release];
	[operationQueue addOperation: op];
	[op release];
	return op;	
}



- (void) getAccessTokenWithDelegate: (id) delegate callbackSelector: (SEL) selector {
	if (!accessToken) {
		cdelegate = delegate; cselector = selector;
		[NSThread detachNewThreadSelector: @selector(getAccessTokenThread) toTarget: self withObject: nil];
	}
}

- (void) getAccessTokenThread {
	NSAutoreleasePool* pool = [[NSAutoreleasePool alloc] init];
	if (requestToken) {
		[requestToken release];
		requestToken = nil;
	}
	
	OAMutableURLRequest *request = [[OAMutableURLRequest alloc] initWithURL: [NSURL URLWithString: getReqTokenURL]
																   consumer: consumer
																	  token: nil   // we don't have a req Token yet
																	  realm: nil   // our service provider doesn't specify a realm
														  signatureProvider: nil]; // use the default method, HMAC-SHA1		
	[request setHTTPMethod: @"POST"];
	OADataFetcher *fetcher = [[OADataFetcher alloc] init];
	[fetcher fetchDataWithRequest: request
						 delegate: self
				didFinishSelector: @selector(requestTokenTicket:didFinishWithData:)
				  didFailSelector: @selector(queryFailed:didFailWithError:)];	
	[request release];
	[fetcher release];
	
	[pool release];
}

- (void)requestTokenTicket:(OAServiceTicket *)ticket didFinishWithData:(NSData *)data {
	if (ticket.didSucceed) {
		NSString *responseBody = [[NSString alloc] initWithData:data
													   encoding:NSUTF8StringEncoding];
	//	NSLog(@"request token: %@", responseBody);

		requestToken = [[OAToken alloc] initWithHTTPResponseBody:responseBody];
		OAMutableURLRequest *request = [[OAMutableURLRequest alloc] initWithURL: [NSURL URLWithString: getAccTokenURL]
																	   consumer: consumer
																		  token: requestToken   
																		  realm: nil   // our service provider doesn't specify a realm
															  signatureProvider: nil]; // use the default method, HMAC-SHA1
		
		[request setHTTPMethod:@"POST"];		
		
		OADataFetcher *fetcher = [[OADataFetcher alloc] init];
		[fetcher fetchDataWithRequest: request
							 delegate: self
					didFinishSelector: @selector(accessTokenTicket:didFinishWithData:)
					  didFailSelector: @selector(queryFailed:didFailWithError:)];			
		[request release];
		[fetcher release];
		
	}
}

- (void)accessTokenTicket:(OAServiceTicket *)ticket didFinishWithData:(NSData *)data {
	if (ticket.didSucceed) {
		NSString *responseBody = [[NSString alloc] initWithData:data
													   encoding:NSUTF8StringEncoding];
		accessToken = [[OAToken alloc] initWithHTTPResponseBody: responseBody];
		[responseBody release];
	} else accessToken = nil;
	
	if ([cdelegate respondsToSelector: cselector]) [cdelegate performSelector: cselector withObject: accessToken];
}

- (void) queryFailed: (OAServiceTicket *)ticket didFailWithError: (NSError*) err {
	if ([cdelegate respondsToSelector: cselector]) {
		[cdelegate performSelector: cselector withObject: accessToken];
	}
}

@end
